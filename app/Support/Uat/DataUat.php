<?php

namespace App\Support\Uat;

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Kabar\AjukanKabar;
use App\Actions\Kabar\SimpanKabar;
use App\Actions\Kabar\TerbitkanKabar;
use App\Actions\Kabar\TolakKabar;
use App\Actions\Lpj\IsiLpj;
use App\Actions\Lpj\NilaiLpj;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\TolakPermohonan;
use App\Actions\Permohonan\TransisiPermohonan;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Enums\StatusPermohonan;
use App\Models\Galeri;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Kabar;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\User;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Data REKAAN untuk uji penerimaan (UAT) dan panduan: akun per peran, tiga ormawa, surat masuk, permohonan di setiap tahap,
 * LPJ, kabar, dan galeri. Dibangun lewat Action sungguhan agar riwayat dan aturan bisnisnya sah. Idempoten per surel akun:
 * menjalankan ulang tidak menggandakan akun, tetapi data transaksi ditambahkan hanya bila ormawa uji belum punya permohonan.
 * TIDAK PERNAH dipakai di produksi (pemanggil menjaga lingkungan). Tidak ada data pribadi asli.
 */
class DataUat
{
    public const PERAN = [
        'admin' => ['admin-persuratan', 'Admin Persuratan'],
        'operator' => ['operator-layanan', 'Operator Layanan'],
        'dekan' => ['dekan', 'Dekan'],
        'wd-akademik' => ['wakil-dekan', 'Wakil Dekan Akademik'],
        'wd-umum' => ['wakil-dekan', 'Wakil Dekan Umum dan Keuangan'],
        'wd-kemahasiswaan' => ['wakil-dekan', 'Wakil Dekan Kemahasiswaan'],
        'kasubag' => ['kasubag', 'Kepala Subbagian Umum'],
        'pembina' => ['pembina-ormawa', 'Dosen Pembina'],
        'pegawai' => ['pegawai', 'Pegawai Penerima Disposisi'],
    ];

    private const JABATAN = ['dekan' => 'dekan', 'wd-akademik' => 'wd-akademik', 'wd-umum' => 'wd-umum-keuangan', 'wd-kemahasiswaan' => 'wd-kemahasiswaan', 'kasubag' => 'kasubag-umum'];

    private const ORMAWA = [
        'matematika' => ['HIMA Uji Matematika', 'prodi'], 'bem' => ['BEM Uji FKIP', 'fakultas'], 'seni' => ['UKM Uji Seni', 'ukm'],
    ];

    /** @var array<string, User> */
    private array $akun = [];

    /** @var list<array{surel: string, peran: string, sandi: ?string}> */
    private array $laporan = [];

    /** @var array<string, int> */
    private array $ringkasan = [];

    public function __construct(private readonly string $awalan, private readonly string $domain, private readonly Closure $sandi) {}

    /** @return array{akun: list<array{surel: string, peran: string, sandi: ?string}>, ringkasan: array<string, int>} */
    public function siapkan(): array
    {
        // Menyiapkan data tidak boleh mengirim surel/WhatsApp ke alamat rekaan.
        Notification::fake();
        app(DatabaseSeeder::class)->run();
        Notification::fake();

        $this->buatAkun();
        $ormawa = $this->buatOrmawa();
        $this->buatSurat();
        $this->buatPermohonan($ormawa);
        $this->buatKonten($ormawa);

        return ['akun' => $this->laporan, 'ringkasan' => $this->ringkasan];
    }

    private function surel(string $kunci): string
    {
        return "{$this->awalan}.{$kunci}@{$this->domain}";
    }

    private function buatAkun(): void
    {
        foreach (self::PERAN as $kunci => [$peran, $nama]) {
            $this->akun[$kunci] = $this->pengguna($kunci, $peran, "UAT {$nama}", '99'.str_pad((string) (count($this->akun) + 1), 16, '0', STR_PAD_LEFT));
        }

        foreach (self::JABATAN as $kunci => $kode) {
            $jabatan = Jabatan::firstWhere('kode', $kode);

            if ($jabatan !== null && ! PemangkuJabatan::where('jabatan_id', $jabatan->getKey())->exists()) {
                PemangkuJabatan::create(['jabatan_id' => $jabatan->getKey(), 'user_id' => $this->akun[$kunci]->getKey(), 'mulai' => now()->startOfYear()->toDateString()]);
            }
        }

        $this->akun['operator']->givePermissionTo(['kabar.kelola', 'galeri.kelola', 'ruangan.kelola-jadwal']);
    }

    private function pengguna(string $kunci, string $peran, string $nama, ?string $nipNim): User
    {
        $surel = $this->surel($kunci);
        $user = User::firstWhere('email', $surel);
        $sandi = null;

        if ($user === null) {
            $sandi = ($this->sandi)();
            $user = new User;
            $user->forceFill(['name' => $nama, 'email' => $surel, 'password' => $sandi, 'nip_nim' => $nipNim, 'aktif' => true, 'email_verified_at' => now(), 'sumber_id_lama' => null])->save();
        }

        $user->syncRoles([$peran]);
        $this->laporan[] = ['surel' => $surel, 'peran' => $peran, 'sandi' => $sandi];

        return $user;
    }

    /** @return array<string, array{ormawa: Ormawa, ketua: User}> */
    private function buatOrmawa(): array
    {
        $hasil = [];
        $i = 0;

        foreach (self::ORMAWA as $kunci => [$nama, $tingkat]) {
            $o = Ormawa::firstOrCreate(['nama' => $nama], ['tingkat' => $tingkat, 'singkatan' => strtoupper(substr($kunci, 0, 4)), 'visi' => 'Visi rekaan untuk uji penerimaan.', 'misi' => 'Misi rekaan untuk uji penerimaan.', 'pembina_user_id' => $this->akun['pembina']->getKey()]);
            $o->sk()->firstOrCreate(['nomor_sk' => 'SK-UAT/'.strtoupper($kunci)], ['tanggal_sk' => now()->startOfYear()->toDateString(), 'periode_mulai' => now()->startOfYear()->toDateString(), 'periode_selesai' => now()->endOfYear()->toDateString()]);
            $ketua = $this->pengguna("pengurus-{$kunci}", 'pengurus-ormawa', "UAT Ketua {$nama}", '88'.str_pad((string) (++$i), 8, '0', STR_PAD_LEFT));
            PengurusOrmawa::firstOrCreate(['ormawa_id' => $o->getKey(), 'user_id' => $ketua->getKey()], ['nama' => $ketua->name, 'jabatan' => 'ketua', 'nim' => $ketua->nip_nim, 'prodi' => 'Program Studi Uji', 'narahubung' => true]);
            $hasil[$kunci] = ['ormawa' => $o, 'ketua' => $ketua];
        }

        $this->ringkasan['ormawa'] = count($hasil);

        return $hasil;
    }

    private function buatSurat(): void
    {
        if (SuratMasuk::where('asal', 'like', 'Instansi Uji%')->exists()) {
            return;
        }

        $surat = fn (string $asal, string $perihal, string $keamanan, string $kecepatan = 'biasa') => app(RegistrasiSuratMasuk::class)->jalankan([
            'nomor_surat' => 'UAT/'.random_int(100, 999).'/'.now()->year, 'tanggal_surat' => now()->subDays(2)->toDateString(), 'asal' => "Instansi Uji {$asal}", 'perihal' => $perihal,
            'klasifikasi_keamanan' => $keamanan, 'derajat_kecepatan' => $kecepatan, 'pindaian_url' => 'https://drive.google.com/file/d/1UatPindaian'.random_int(1000, 9999).'AbCdEf/view',
        ], $this->akun['admin']);

        $surat('A', 'Undangan rapat koordinasi (rekaan)', 'biasa');
        $surat('B', 'Permintaan data akreditasi (rekaan)', 'terbatas', 'segera');
        $surat('C', 'Surat rahasia contoh (rekaan)', 'rahasia');
        $didisposisi = $surat('D', 'Permohonan narasumber seminar (rekaan)', 'biasa', 'segera');

        app(BuatDisposisi::class)->jalankan($didisposisi, $this->akun['dekan'], [$this->akun['wd-akademik']->getKey(), $this->akun['kasubag']->getKey(), $this->akun['pegawai']->getKey()], ['tindak_lanjuti', 'siapkan_jawaban'], 'Mohon dipelajari dan ditindaklanjuti (data rekaan).', now()->addDays(3));
        $this->ringkasan['surat_masuk'] = 4;
    }

    /** @param  array<string, array{ormawa: Ormawa, ketua: User}>  $ormawa */
    private function buatPermohonan(array $ormawa): void
    {
        if (Permohonan::whereIn('ormawa_id', collect($ormawa)->pluck('ormawa.id'))->exists()) {
            return;
        }

        $jenis = JenisPermohonan::firstWhere('kode', 'kegiatan');
        $wdIds = Jabatan::whereIn('kode', ['wd-akademik', 'wd-kemahasiswaan'])->pluck('id')->all();
        $n = 0;

        $ajukan = function (string $kunci, string $nama) use ($ormawa, $jenis, &$n): Permohonan {
            $o = $ormawa[$kunci];
            RateLimiter::clear('ajukan-permohonan:'.$o['ketua']->getKey());
            $n++;

            return app(AjukanPermohonan::class)->jalankan($o['ketua'], $o['ormawa'], $jenis, [
                'nama_kegiatan' => $nama, 'perihal' => "Izin kegiatan {$nama}", 'nomor_surat_ormawa' => "UAT/{$n}/2026", 'tanggal_mulai' => now()->addDays(30 + $n)->toDateString(),
                'tanggal_selesai' => now()->addDays(31 + $n)->toDateString(), 'jam_mulai' => '08:00', 'jam_selesai' => '15:00', 'deskripsi' => 'Kegiatan rekaan untuk uji penerimaan.',
                'penanggung_jawab' => ['ketua' => ['nama' => $o['ketua']->name, 'prodi' => 'Program Studi Uji']],
                'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1UatSurat'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'AbCdEf/view', 'proposal' => 'https://drive.google.com/file/d/1UatProposal'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'AbCd/view'],
            ]);
        };
        $validasi = fn (Permohonan $p) => app(ValidasiPermohonan::class)->jalankan($p, $this->akun['admin']);
        $disposisi = fn (Permohonan $p) => app(DisposisiPermohonan::class)->jalankan($p->fresh(), $this->akun['dekan'], $wdIds);
        $putusan = function (Permohonan $p) {
            app(PutusanWd::class)->jalankan($p->fresh(), $this->akun['wd-akademik'], 'setuju');
            app(PutusanWd::class)->jalankan($p->fresh(), $this->akun['wd-kemahasiswaan'], 'setuju');
        };

        $ajukan('matematika', 'Seminar Pendidikan (tahap validasi)');
        $validasi($ajukan('matematika', 'Workshop Statistika (tahap disposisi dekan)'));
        $disposisi($validasi($ajukan('bem', 'Lomba Debat (tahap persetujuan WD)')));
        $p = $ajukan('bem', 'Bakti Sosial (tahap rekomendasi kasubag)');
        $disposisi($validasi($p));
        $putusan($p);
        $p = $ajukan('seni', 'Pentas Seni (tahap penerbitan surat)');
        $disposisi($validasi($p));
        $putusan($p);
        app(RekomendasiKasubag::class)->jalankan($p->fresh(), $this->akun['kasubag']);
        app(KembalikanPermohonan::class)->jalankan($ajukan('seni', 'Pameran Lukisan (dikembalikan)'), $this->akun['admin'], 'Lengkapi proposal anggaran (data rekaan).');
        app(TolakPermohonan::class)->jalankan($ajukan('matematika', 'Studi Banding (ditolak)'), $this->akun['admin'], 'Jadwal bentrok (data rekaan).');

        // Kegiatan selesai berizin → LPJ: satu draf terlambat, satu diajukan, satu dinilai lengkap.
        foreach ([['matematika', 'Pelatihan Guru (LPJ terlambat)', 'terlambat'], ['bem', 'Seminar Karier (LPJ diajukan)', 'diajukan'], ['seni', 'Festival Seni (LPJ dinilai)', 'dinilai']] as [$kunci, $nama, $keadaan]) {
            $p = $ajukan($kunci, $nama);
            $disposisi($validasi($p));
            $putusan($p);
            app(RekomendasiKasubag::class)->jalankan($p->fresh(), $this->akun['kasubag']);
            $this->selesaikan($p, $keadaan);
        }

        $this->ringkasan['permohonan'] = Permohonan::whereIn('ormawa_id', collect($ormawa)->pluck('ormawa.id'))->count();
        $this->ringkasan['lpj'] = Lpj::count();
    }

    private function selesaikan(Permohonan $p, string $keadaan): void
    {
        $p = $p->fresh();
        $mulai = $keadaan === 'terlambat' ? now()->subDays(60) : now()->subDays(8);
        $p->forceFill(['tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $mulai->addDay()->toDateString()])->saveQuietly();
        app(TransisiPermohonan::class)->dalamKunci($p, fn (Permohonan $s) => app(TransisiPermohonan::class)->ke($s, StatusPermohonan::Selesai, $this->akun['admin']));
        $lpj = Lpj::where('permohonan_id', $p->getKey())->firstOrFail();
        $lpj->forceFill(['batas_waktu' => $p->fresh()->tanggal_selesai->addDays(14)->toDateString()])->saveQuietly();

        if ($keadaan === 'terlambat') {
            return;
        }

        $ketua = User::find($p->diajukan_oleh);
        app(IsiLpj::class)->jalankan($lpj->fresh(), $ketua, [
            'tanggal_pelaksanaan' => $mulai->toDateString(), 'jumlah_peserta' => 80, 'ringkasan' => 'Kegiatan berjalan lancar (data rekaan).',
            'berkas_lpj' => 'https://drive.google.com/file/d/1UatLpj'.random_int(1000, 9999).'AbCdEfGh/view', 'tautan_instagram' => 'https://www.instagram.com/p/UatContoh123/',
        ]);

        if ($keadaan === 'dinilai') {
            $isian = ['ketepatan' => ['nilai' => 18, 'catatan' => 'Tepat waktu'], 'kepatuhan' => ['nilai' => 5]];
            foreach (['dekan', 'wd-akademik', 'wd-kemahasiswaan'] as $k) {
                app(NilaiLpj::class)->jalankan($lpj->fresh(), $this->akun[$k], $isian);
            }
            app(NilaiLpj::class)->jalankan($lpj->fresh(), $this->akun['kasubag'], ['kelengkapan' => ['nilai' => 17], 'kontribusi_fakultas' => ['nilai' => 4, 'catatan' => 'Baik']]);
        }
    }

    /** @param  array<string, array{ormawa: Ormawa, ketua: User}>  $ormawa */
    private function buatKonten(array $ormawa): void
    {
        if (Kabar::where('judul', 'like', '%(rekaan)%')->exists()) {
            return;
        }

        $kabar = fn (string $kunci, string $judul) => app(SimpanKabar::class)->jalankan($ormawa[$kunci]['ketua'], ['ormawa_id' => $ormawa[$kunci]['ormawa']->getKey(), 'judul' => $judul, 'subjudul' => 'Kabar rekaan', 'isi' => '<p>Ini kabar rekaan untuk uji penerimaan dan panduan.</p>', 'tag' => ['uat', 'rekaan']]);

        $terbit = $kabar('matematika', 'Seminar Pendidikan Sukses (rekaan)');
        app(AjukanKabar::class)->jalankan($terbit, $ormawa['matematika']['ketua']);
        app(TerbitkanKabar::class)->jalankan($terbit->fresh(), $this->akun['admin']);
        app(AjukanKabar::class)->jalankan($kabar('bem', 'Pendaftaran Pengurus Baru (rekaan)'), $ormawa['bem']['ketua']);
        $kabar('seni', 'Draf Pentas Seni (rekaan)');
        $tolak = $kabar('seni', 'Pengumuman Lomba (rekaan)');
        app(AjukanKabar::class)->jalankan($tolak, $ormawa['seni']['ketua']);
        app(TolakKabar::class)->jalankan($tolak->fresh(), $this->akun['admin'], 'Tambahkan foto kegiatan (data rekaan).');

        Galeri::firstOrCreate(['judul' => 'Foto Seminar (rekaan)'], ['tipe' => 'foto', 'url' => 'https://drive.google.com/file/d/1UatFotoSeminar12345/view', 'aktif' => true, 'ormawa_id' => $ormawa['matematika']['ormawa']->getKey()]);
        Galeri::firstOrCreate(['judul' => 'Video Festival (rekaan)'], ['tipe' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'aktif' => true, 'ormawa_id' => $ormawa['seni']['ormawa']->getKey()]);
        Galeri::firstOrCreate(['judul' => 'Instagram Festival (rekaan)'], ['tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/UatContoh123/', 'aktif' => true]);

        $this->ringkasan['kabar'] = Kabar::count();
    }
}
