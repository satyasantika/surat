<?php

namespace App\Actions\Kiriman;

use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisPengirim;
use App\Enums\StatusSuratMasuk;
use App\Models\Ormawa;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Rules\TautanBerkasValid;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengaturan;
use App\Support\TujuanUsulan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Pengirim eksternal (akun Google di luar domain mahasiswa) mengirim surat kepada fakultas dan/atau ormawa/prodi/lab.
 * Satu pengiriman = satu kiriman per tujuan, semuanya dibuat dalam satu transaksi (satu tujuan tak sah = tidak ada
 * yang dibuat) dan sesudahnya berjalan mandiri:
 * - `fakultas`: surat masuk berstatus `diajukan`, belum bernomor agenda dan belum diregistrasi; nomor agenda diberikan
 *   saat petugas menerima (TerimaKirimanEksternal).
 * - `ormawa:{uuid}` / `unit:{uuid}` (lihat TujuanUsulan): satu baris usulan per tujuan dengan asal `eksternal` dan
 *   pengusul = akun pengirim, masuk ke kotak usulan perantara yang sama dengan usulan mahasiswa. Paling banyak
 *   `batas_tujuan_kiriman` tujuan perantara per pengiriman.
 * Tujuan ganda dianggap satu. Penyalahgunaan dibatasi `batas_kiriman_harian` pengiriman per akun per 24 jam (bukan per
 * tujuan); pengiriman yang gagal validasi tidak dihitung.
 */
class AjukanKirimanEksternal
{
    public const TUJUAN_FAKULTAS = 'fakultas';

    public function __construct(private readonly PenyimpananBerkas $berkas) {}

    /**
     * @param  array<string, mixed>  $data  tujuan (list), nama, instansi, perihal, uraian, surel/wa (minimal satu), lampiran (opsional, tautan)
     * @return array{surat: ?SuratMasuk, usulan: list<UsulanMahasiswa>}
     */
    public function jalankan(User $pengirim, array $data): array
    {
        if (! $pengirim->can('kiriman.ajukan')) {
            throw new AuthorizationException('Hanya pengirim eksternal yang dapat mengirim surat lewat halaman ini.');
        }

        $kunci = 'kiriman-eksternal:'.$pengirim->getKey();
        $batas = max(1, (int) Pengaturan::get('batas_kiriman_harian', 5));

        if (RateLimiter::tooManyAttempts($kunci, $batas)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($kunci), 'Batas pengiriman harian tercapai. Coba lagi besok.');
        }

        $valid = Validator::make($data, [
            'tujuan' => ['required', 'array', 'min:1'],
            'tujuan.*' => ['required', 'string', 'regex:/^(fakultas|(ormawa|unit):[0-9a-f-]{36})$/'],
            'nama' => ['nullable', 'string', 'max:150'],
            'instansi' => ['nullable', 'string', 'max:255'],
            'perihal' => ['required', 'string', 'max:500'],
            'uraian' => ['required', 'string', 'max:5000'],
            'surel' => ['nullable', 'email', 'max:150', Rule::requiredIf(blank($data['wa'] ?? null))],
            'wa' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9\s-]{7,18}$/', Rule::requiredIf(blank($data['surel'] ?? null))],
            'lampiran' => ['nullable', 'string', new TautanBerkasValid],
        ], [
            'tujuan.required' => 'Pilih tujuan kiriman.',
            'tujuan.min' => 'Pilih tujuan kiriman.',
            'tujuan.*.regex' => 'Tujuan kiriman tidak valid.',
            'surel.required' => 'Isi surel atau nomor WhatsApp agar kami dapat menghubungi Anda.',
            'wa.required' => 'Isi surel atau nomor WhatsApp agar kami dapat menghubungi Anda.',
            'wa.regex' => 'Nomor WhatsApp tidak valid.',
        ])->validate();

        $tujuan = array_values(array_unique($valid['tujuan']));
        $keFakultas = in_array(self::TUJUAN_FAKULTAS, $tujuan, true);
        $perantara = $this->perantara(array_values(array_diff($tujuan, [self::TUJUAN_FAKULTAS])), (string) $valid['perihal']);

        // Hanya pengiriman yang lolos validasi yang dihitung terhadap batas harian.
        RateLimiter::hit($kunci, 86400);

        return DB::transaction(function () use ($pengirim, $valid, $keFakultas, $perantara) {
            $nama = filled($valid['nama'] ?? null) ? $valid['nama'] : $pengirim->name;
            $instansi = filled($valid['instansi'] ?? null) ? $valid['instansi'] : null;

            $surat = $keFakultas ? $this->keFakultas($pengirim, $valid, $nama, $instansi) : null;
            $usulan = [];

            foreach ($perantara as $tujuan) {
                $usulan[] = $this->kePerantara($pengirim, $valid, $tujuan, $instansi);
            }

            return ['surat' => $surat, 'usulan' => $usulan];
        });
    }

    /**
     * Tujuan perantara yang sah (urutan masukan). Batas jumlah lebih dulu agar daftar panjang tidak memicu banyak kueri.
     *
     * @param  list<string>  $tujuan
     * @return list<Ormawa|UnitKerja>
     */
    private function perantara(array $tujuan, string $perihal): array
    {
        if ($tujuan === []) {
            return [];
        }

        $batas = max(1, (int) Pengaturan::get('batas_tujuan_kiriman', 5));

        if (count($tujuan) > $batas) {
            throw ValidationException::withMessages(['tujuan' => "Paling banyak {$batas} tujuan ormawa/program studi/laboratorium per pengiriman."]);
        }

        // Kotak usulan perantara menyimpan perihal hingga 255 karakter.
        if (mb_strlen($perihal) > 255) {
            throw ValidationException::withMessages(['perihal' => 'Perihal paling banyak 255 karakter bila dikirim ke ormawa/program studi/laboratorium.']);
        }

        return array_map(fn (string $t) => TujuanUsulan::urai($t), $tujuan);
    }

    /** @param  array<string, mixed>  $valid */
    private function kePerantara(User $pengirim, array $valid, Ormawa|UnitKerja $tujuan, ?string $instansi): UsulanMahasiswa
    {
        $usulan = new UsulanMahasiswa(['perihal' => $valid['perihal'], 'uraian' => $valid['uraian'], 'instansi' => $instansi]);
        $usulan->id = $usulan->newUniqueId();
        $usulan->mahasiswa_id = $pengirim->getKey();
        $usulan->asal = UsulanMahasiswa::ASAL_EKSTERNAL;
        $usulan->ormawa_id = $tujuan instanceof Ormawa ? $tujuan->getKey() : null;
        $usulan->unit_kerja_id = $tujuan instanceof UnitKerja ? $tujuan->getKey() : null;
        $usulan->save();

        if (filled($valid['lampiran'] ?? null)) {
            $this->berkas->simpan($usulan, 'lampiran', $valid['lampiran'], null, $pengirim);
        }

        app(NotifikasiAlur::class)->usulanDiajukan($usulan, $pengirim);

        return $usulan;
    }

    /** @param  array<string, mixed>  $valid */
    private function keFakultas(User $pengirim, array $valid, string $nama, ?string $instansi): SuratMasuk
    {
        $surat = new SuratMasuk([
            'tanggal_terima' => now(),
            'tanggal_surat' => today(),
            'asal' => $instansi ?? $nama,
            'perihal' => $valid['perihal'],
            'ringkasan' => $valid['uraian'],
            'jenis_pengirim' => JenisPengirim::Eksternal->value,
            'pengirim_nama' => $nama,
            'pengirim_instansi' => $instansi,
            'pengirim_surel' => filled($valid['surel'] ?? null) ? $valid['surel'] : null,
            'pengirim_wa' => filled($valid['wa'] ?? null) ? $valid['wa'] : null,
        ]);
        $surat->id = $surat->newUniqueId();
        $surat->status = StatusSuratMasuk::Diajukan;
        $surat->pengirim_user_id = $pengirim->getKey();
        $surat->save();

        if (filled($valid['lampiran'] ?? null)) {
            $this->berkas->simpan($surat, 'pindaian', $valid['lampiran'], 'Berkas kiriman pengirim', $pengirim);
        }

        app(NotifikasiAlur::class)->kirimanEksternalBaru($surat, $pengirim);

        return $surat;
    }
}
