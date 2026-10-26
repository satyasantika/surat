<?php

namespace App\Actions\Kiriman;

use App\Contracts\PenyimpananBerkas;
use App\Enums\JenisPengirim;
use App\Enums\StatusSuratMasuk;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Rules\TautanBerkasValid;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Pengirim eksternal (akun Google di luar domain mahasiswa) mengirim surat kepada fakultas. Hasilnya surat masuk
 * berstatus `diajukan`: belum bernomor agenda dan belum diregistrasi; nomor agenda diberikan saat petugas menerima
 * (TerimaKirimanEksternal). Satu pengiriman = satu kiriman per tujuan; tahap ini hanya tujuan `fakultas`.
 * Penyalahgunaan dibatasi `batas_kiriman_harian` pengiriman per akun per 24 jam (bukan per tujuan).
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
            'tujuan.*' => ['required', 'string', 'distinct', Rule::in([self::TUJUAN_FAKULTAS])],
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
            'tujuan.*.in' => 'Tujuan kiriman tidak valid.',
            'tujuan.*.distinct' => 'Tujuan kiriman tidak boleh ganda.',
            'surel.required' => 'Isi surel atau nomor WhatsApp agar kami dapat menghubungi Anda.',
            'wa.required' => 'Isi surel atau nomor WhatsApp agar kami dapat menghubungi Anda.',
            'wa.regex' => 'Nomor WhatsApp tidak valid.',
        ])->validate();

        // Hanya pengiriman yang lolos validasi yang dihitung terhadap batas harian.
        RateLimiter::hit($kunci, 86400);

        return DB::transaction(function () use ($pengirim, $valid) {
            $nama = filled($valid['nama'] ?? null) ? $valid['nama'] : $pengirim->name;
            $instansi = filled($valid['instansi'] ?? null) ? $valid['instansi'] : null;

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

            return ['surat' => $surat, 'usulan' => []];
        });
    }
}
