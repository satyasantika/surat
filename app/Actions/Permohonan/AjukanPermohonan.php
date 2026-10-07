<?php

namespace App\Actions\Permohonan;

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Contracts\LayananRuangan;
use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusPermohonan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RegisterNomor;
use App\Models\RiwayatPermohonan;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Services\Ruangan\KetersediaanRuangan;
use App\Support\KunciRuangan;
use App\Support\Pengaturan;
use App\Support\SesiRuangan;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Pengajuan permohonan ormawa (BR-02, BR-11–BR-16): hanya pengurus aktif ber-SK berlaku, tidak diblokir LPJ,
 * nomor dari register, tautan berkas wajib, dan seluruh ruangan×tanggal×sesi ditahan atomik di dalam kunci.
 */
class AjukanPermohonan
{
    public const BATAS_PER_JAM = 10;

    public function __construct(
        private readonly AmbilNomorBerikutnya $nomor,
        private readonly LayananRuangan $layanan,
        private readonly KetersediaanRuangan $ketersediaan,
        private readonly PenyimpananBerkas $berkas,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function jalankan(User $pelaku, Ormawa $ormawa, JenisPermohonan $jenis, array $data): Permohonan
    {
        $this->batasiLaju($pelaku);
        $this->otorisasi($pelaku, $ormawa);

        $valid = $this->validasi($jenis, $data);
        $valid['penanggung_jawab'] = $this->bersihkanPenanggungJawab($valid['penanggung_jawab']);
        $ruangan = $jenis->butuh_ruangan ? $this->validasiRuangan($valid) : [];

        $butir = collect($ruangan)->map(fn (array $r) => ['kode' => $r['kode'], 'tanggal' => $r['tanggal']])->all();

        return KunciRuangan::dengan($butir, fn () => DB::transaction(function () use ($pelaku, $ormawa, $jenis, $valid, $ruangan) {
            $this->pastikanTersedia($ruangan);

            $sekarang = now();
            $permohonan = new Permohonan(collect($valid)->only([
                'nama_kegiatan', 'perihal', 'nomor_surat_ormawa', 'tanggal_mulai', 'tanggal_selesai', 'jam_mulai', 'jam_selesai',
                'tempat_lain', 'deskripsi', 'penanggung_jawab', 'fasilitas_rektorat', 'alasan_mendesak',
            ])->all());
            $permohonan->id = $permohonan->newUniqueId();
            $permohonan->ormawa_id = $ormawa->getKey();
            $permohonan->jenis_permohonan_id = $jenis->getKey();
            $permohonan->diajukan_oleh = $pelaku->getKey();
            $permohonan->diajukan_pada = $sekarang;
            $permohonan->status = StatusPermohonan::Diajukan;
            $permohonan->nomor = $this->nomor->jalankan(RegisterNomor::where('kode', 'permohonan')->firstOrFail(), $permohonan, [], $sekarang)->nomor_lengkap;
            $permohonan->save();

            foreach ($ruangan as $r) {
                PermohonanRuangan::create([
                    'permohonan_id' => $permohonan->getKey(), 'kode_ruangan' => $r['kode'], 'nama_ruangan' => $r['nama'],
                    'tanggal' => $r['tanggal'], 'sesi' => $r['sesi'], 'status' => PermohonanRuangan::DITAHAN,
                ]);
            }

            foreach ($jenis->berkas_wajib as $kunci) {
                $this->berkas->simpan($permohonan, $kunci, $valid['berkas'][$kunci], null, $pelaku);
            }

            $this->catat($permohonan, null, StatusPermohonan::Diajukan, $pelaku, null);

            $tujuan = Pengaturan::get('persetujuan_pembina_aktif') && $ormawa->pembina_user_id !== null
                ? StatusPermohonan::PersetujuanPembina
                : StatusPermohonan::ValidasiAdmin;

            $permohonan->status = $tujuan;
            $permohonan->save();
            $this->catat($permohonan, StatusPermohonan::Diajukan, $tujuan, $pelaku, null);

            return $permohonan;
        }));
    }

    private function batasiLaju(User $pelaku): void
    {
        $kunci = 'ajukan-permohonan:'.$pelaku->getKey();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PER_JAM)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($kunci), 'Terlalu banyak percobaan pengajuan. Coba lagi nanti.');
        }

        RateLimiter::hit($kunci, 3600);
    }

    private function otorisasi(User $pelaku, Ormawa $ormawa): void
    {
        if (! $pelaku->can('permohonan.ajukan') || ! $pelaku->ormawaAktif()->contains('id', $ormawa->getKey())) {
            throw new AuthorizationException('Hanya pengurus aktif dengan SK berlaku yang dapat mengajukan atas nama ormawa ini.');
        }

        if ($ormawa->diblokir_lpj && Pengaturan::get('blokir_lpj_terlambat')) {
            throw ValidationException::withMessages(['ormawa' => 'Ormawa diblokir karena LPJ terlambat. Lengkapi LPJ sebelum mengajukan permohonan baru.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validasi(JenisPermohonan $jenis, array $data): array
    {
        if (! $jenis->aktif) {
            throw ValidationException::withMessages(['jenis' => 'Jenis permohonan tidak aktif.']);
        }

        $aturan = [
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'perihal' => ['required', 'string', 'max:255'],
            'nomor_surat_ormawa' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai'],
            'tempat_lain' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['required', 'string', 'max:10000'],
            'alasan_mendesak' => ['nullable', 'string', 'max:5000'],
            'penanggung_jawab' => ['required', 'array'],
            'penanggung_jawab.ketua' => ['required', 'array'],
            'penanggung_jawab.ketua.nama' => ['required', 'string', 'max:150'],
            'penanggung_jawab.ketua.nim' => ['nullable', 'string', 'max:20'],
            'penanggung_jawab.ketua.hp' => ['nullable', 'string', 'max:20'],
            'penanggung_jawab.ketua.prodi' => ['nullable', 'string', 'max:100'],
            'penanggung_jawab.ketua.email' => ['nullable', 'email', 'max:150'],
            'penanggung_jawab.wakil' => ['nullable', 'array'],
            'penanggung_jawab.wakil.nama' => ['nullable', 'string', 'max:150'],
            'penanggung_jawab.sekretaris' => ['nullable', 'array'],
            'penanggung_jawab.sekretaris.nama' => ['nullable', 'string', 'max:150'],
            'berkas' => ['array'],
            'ruangan' => ['nullable', 'array'],
            'fasilitas_rektorat' => [$jenis->butuh_fasilitas_rektorat ? 'required' : 'nullable', 'array', $jenis->butuh_fasilitas_rektorat ? 'min:1' : 'max:0'],
            'fasilitas_rektorat.*.nama' => ['required', 'string', 'max:150'],
            'fasilitas_rektorat.*.jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
            'fasilitas_rektorat.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($jenis->berkas_wajib as $kunci) {
            $aturan["berkas.{$kunci}"] = ['required', 'string', new TautanBerkasValid];
        }

        $valid = Validator::make($data, $aturan, [
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai tidak boleh di masa lalu.',
            'fasilitas_rektorat.required' => 'Jenis permohonan ini mewajibkan rincian fasilitas rektorat.',
            'fasilitas_rektorat.max' => 'Jenis permohonan ini tidak memuat fasilitas rektorat.',
        ])->validate();

        // BR-15: pengajuan terlambat butuh alasan.
        $minHari = (int) Pengaturan::get('min_hari_sebelum_kegiatan');
        $sisaHari = (int) now()->startOfDay()->diffInDays(CarbonImmutable::parse($valid['tanggal_mulai'])->startOfDay(), false);

        if ($sisaHari < $minHari && blank($valid['alasan_mendesak'] ?? null)) {
            throw ValidationException::withMessages([
                'alasan_mendesak' => "Permohonan harus diajukan paling lambat {$minHari} hari sebelum kegiatan; isi alasan mendesak.",
            ]);
        }

        return $valid;
    }

    /**
     * Hanya bidang yang dikenal yang disimpan (data pribadi, BR-18).
     *
     * @param  array<string, mixed>  $pj
     * @return array<string, array<string, mixed>>
     */
    private function bersihkanPenanggungJawab(array $pj): array
    {
        $bidang = ['nama', 'nim', 'hp', 'prodi', 'email'];
        $hasil = [];

        foreach (['ketua', 'wakil', 'sekretaris'] as $peran) {
            if (! empty($pj[$peran]['nama'])) {
                $hasil[$peran] = array_intersect_key($pj[$peran], array_flip($bidang));
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $valid
     * @return list<array{kode: string, nama: string, tanggal: string, sesi: string}>
     */
    private function validasiRuangan(array $valid): array
    {
        $butir = $valid['ruangan'] ?? [];

        if ($butir === []) {
            throw ValidationException::withMessages(['ruangan' => 'Pilih minimal satu ruangan dan sesi.']);
        }

        $katalog = collect($this->layanan->daftar())->keyBy('kode');
        $mulai = CarbonImmutable::parse($valid['tanggal_mulai']);
        $selesai = CarbonImmutable::parse($valid['tanggal_selesai']);
        $hasil = [];

        foreach ($butir as $i => $r) {
            $v = Validator::make($r, [
                'kode' => ['required', 'string', 'max:30'],
                'tanggal' => ['required', 'date_format:Y-m-d'],
                'sesi' => ['required', 'string'],
            ])->validate();

            $tanggal = CarbonImmutable::parse($v['tanggal']);

            if (! $katalog->has($v['kode'])) {
                throw ValidationException::withMessages(["ruangan.{$i}.kode" => "Ruangan {$v['kode']} tidak tersedia."]);
            }

            if (! SesiRuangan::valid($v['sesi'])) {
                throw ValidationException::withMessages(["ruangan.{$i}.sesi" => "Sesi {$v['sesi']} tidak dikenal."]);
            }

            if ($tanggal->lt($mulai) || $tanggal->gt($selesai)) {
                throw ValidationException::withMessages(["ruangan.{$i}.tanggal" => 'Tanggal ruangan harus dalam rentang kegiatan.']);
            }

            $hasil[] = ['kode' => $v['kode'], 'nama' => $katalog[$v['kode']]['nama'], 'tanggal' => $v['tanggal'], 'sesi' => $v['sesi']];
        }

        // Dua butir yang saling bentrok dalam satu pengajuan juga ditolak.
        foreach ($hasil as $a => $x) {
            foreach (array_slice($hasil, $a + 1) as $y) {
                if ($x['kode'] === $y['kode'] && $x['tanggal'] === $y['tanggal'] && SesiRuangan::bentrok($x['sesi'], $y['sesi'])) {
                    throw ValidationException::withMessages(['ruangan' => "Pilihan ruangan saling bentrok pada {$x['tanggal']}."]);
                }
            }
        }

        return $hasil;
    }

    /**
     * Seluruh pengajuan gagal bila satu saja bentrok; pesan menyebut tanggal dan sesi.
     *
     * @param  list<array{kode: string, nama: string, tanggal: string, sesi: string}>  $ruangan
     */
    private function pastikanTersedia(array $ruangan): void
    {
        $bentrok = [];

        foreach ($ruangan as $r) {
            $tanggal = CarbonImmutable::parse($r['tanggal']);
            $dipakai = $this->ketersediaan->terpakai($r['kode'], $r['tanggal'], $r['sesi']);

            if ($dipakai) {
                $bentrok[] = "{$r['nama']} {$tanggal->locale('id')->translatedFormat('d F Y')} sesi {$r['sesi']}";
            }
        }

        if ($bentrok !== []) {
            throw ValidationException::withMessages(['ruangan' => 'Ruangan sudah dipakai: '.implode('; ', $bentrok).'.']);
        }
    }

    private function catat(Permohonan $permohonan, ?StatusPermohonan $dari, StatusPermohonan $ke, User $pelaku, ?string $catatan): void
    {
        RiwayatPermohonan::create([
            'permohonan_id' => $permohonan->getKey(), 'dari_status' => $dari?->value, 'ke_status' => $ke->value,
            'oleh' => $pelaku->getKey(), 'catatan' => $catatan,
        ]);
    }
}
