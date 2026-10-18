<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Masuk\KirimHasilSuratMasuk;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Actions\Masuk\TeruskanSuratKeDekan;
use App\Actions\Masuk\TugaskanPembuatSuratMasuk;
use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\TandaTangani;
use App\Actions\Naskah\TransisiNaskah;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\KirimHasilPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\TerbitkanIzin;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Contracts\GerbangWhatsapp;
use App\Enums\StatusPermohonan;
use App\Enums\StatusSuratMasuk;
use App\Events\NaskahTerbit;
use App\Filament\Resources\Permohonans\Pages\ListPermohonans;
use App\Filament\Resources\Permohonans\Pages\ViewPermohonan;
use App\Filament\Resources\SuratMasuks\Pages\EditSuratMasuk;
use App\Jobs\CatatPemakaianRuangan;
use App\Jobs\KirimHasilKePengirim;
use App\Jobs\TerbitkanNaskah;
use App\Listeners\SelesaikanPermohonanDariNaskah;
use App\Livewire\Ormawa\Progres;
use App\Livewire\Pembuat\Tugas;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Mail\HasilSuratKePengirim;
use App\Models\Aktivitas;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PemakaianRuanganLokal;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RuanganLokal;
use App\Models\SuratMasuk;
use App\Models\TautanBerkas;
use App\Models\UnitKerja;
use App\Models\UnitPembuat;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Services\Naskah\RenderNaskah;
use App\Support\KodeLacak;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const HL_URL_HASIL = 'https://drive.google.com/file/d/1HasilCetakAbCdEfGh/view';

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Mail::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, KlasifikasiArsipSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::firstOrCreate(['kode' => 'A101'], ['nama' => 'Ruang A101']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function hlUser(string $peran, array $atribut = []): User
{
    $u = User::factory()->create($atribut)->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function hlPejabat(string $peran, string $kodeJabatan): User
{
    $jabatan = Jabatan::firstWhere('kode', $kodeJabatan);

    if ($sudah = $jabatan->pemangkuPada(now())) {
        return $sudah->user;
    }

    $u = hlUser($peran, ['name' => 'Pejabat '.Str::headline($kodeJabatan).' Pribadi']);
    PemangkuJabatan::create(['jabatan_id' => $jabatan->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);

    return $u;
}

/** Unit pembuat dengan petugas berperan pegawai (tanpa akses panel). */
function hlUnit(string $kode = 'izin-kegiatan', int $petugas = 1): UnitPembuat
{
    $unit = UnitPembuat::create(['kode' => $kode, 'nama' => 'Unit '.Str::headline($kode)]);

    foreach (range(1, $petugas) as $i) {
        $unit->petugas()->attach(User::factory()->create(['name' => "Petugas {$kode} {$i}"])->assignRole('pegawai')->id);
    }

    return $unit;
}

function hlPetugas(UnitPembuat $unit): User
{
    return $unit->petugas()->orderBy('name')->firstOrFail();
}

/** @return array{0: Permohonan, 1: User, 2: User} permohonan di tahap persetujuan WD -> rekomendasi kasubag, pengaju, kasubag */
function hlPermohonanDiKasubag(string $jenis = 'kegiatan', array $ubah = []): array
{
    $o = Ormawa::create(['nama' => 'HIMA '.random_int(1, 99999), 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(8000000, 8999999), 'name' => 'Pengaju Rahasia Pribadi'])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', $jenis), $ubah + [
        'nama_kegiatan' => 'Seminar Nasional', 'perihal' => 'Izin seminar', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Deskripsi',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi Santoso', 'hp' => '081200099988']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ]);

    $p = app(ValidasiPermohonan::class)->jalankan($p, hlUser('admin-persuratan', ['name' => 'Admin Rahasia Pribadi']));
    $dekan = hlPejabat('dekan', 'dekan');
    $wd = hlPejabat('wakil-dekan', 'wd-akademik');
    app(DisposisiPermohonan::class)->jalankan($p, $dekan, [Jabatan::firstWhere('kode', 'wd-akademik')->id], 'Catatan internal dekan');
    app(PutusanWd::class)->jalankan($p->fresh(), $wd, 'setuju', 'Catatan internal WD');

    return [$p->fresh(), $u, hlPejabat('kasubag', 'kasubag-umum')];
}

/** @return array{0: Permohonan, 1: UnitPembuat, 2: User, 3: User} permohonan di tahap pembuatan */
function hlPermohonanPembuatan(string $jenis = 'kegiatan', array $ubah = []): array
{
    [$p, $pengaju, $kasubag] = hlPermohonanDiKasubag($jenis, $ubah);
    $unit = hlUnit('izin-kegiatan-'.Str::lower(Str::random(6)));
    app(RekomendasiKasubag::class)->jalankan($p, $kasubag, 'Catatan internal kasubag RAHASIA-INTERNAL', $unit->id);

    return [$p->fresh(), $unit, hlPetugas($unit), $pengaju];
}

function hlDataSurat(array $ubah = []): array
{
    return $ubah + [
        'nomor_surat' => '55/UKM/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'UKM Pecinta Alam Kota',
        'perihal' => 'Permohonan kerja sama kegiatan', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
        'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
        'jenis_pengirim' => 'eksternal', 'pengirim_nama' => 'Rani Putri', 'pengirim_instansi' => 'UKM Pecinta Alam Kota',
        'pengirim_surel' => 'rani@contoh.test', 'pengirim_wa' => '081234567890',
    ];
}

/** @return array{0: SuratMasuk, 1: UnitPembuat, 2: User, 3: User, 4: User} surat setelah dekan -> kasubag -> penugasan unit: surat, unit, petugas, kasubag, staf */
function hlSuratDitugaskan(array $ubah = []): array
{
    $staf = hlUser('staf-khusus', ['name' => 'Staf Rahasia Pribadi']);
    $surat = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat($ubah), $staf);
    app(TeruskanSuratKeDekan::class)->jalankan($surat, $staf, 'Catatan penerusan RAHASIA-INTERNAL');
    $dekan = hlPejabat('dekan', 'dekan');
    $kasubag = hlPejabat('kasubag', 'kasubag-umum');
    app(BuatDisposisi::class)->jalankan($surat->fresh(), $dekan, [$kasubag->id], ['tindak_lanjuti'], 'Instruksi internal dekan RAHASIA-INTERNAL');
    $induk = DisposisiPenerima::where('user_id', $kasubag->id)->firstOrFail();
    $unit = hlUnit('surat-tugas-dosen');
    app(TugaskanPembuatSuratMasuk::class)->jalankan($surat->fresh(), $kasubag, $induk, $unit->id, 'Catatan internal kasubag RAHASIA-INTERNAL');

    return [$surat->fresh(), $unit, hlPetugas($unit), $kasubag, $staf];
}

function hlLacak(Permohonan|SuratMasuk $m): string
{
    return '/lacak/'.KodeLacak::tampil($m->kode_lacak);
}

function hlNotifikasi(User $u): array
{
    return Notification::sent($u, NotifikasiTahap::class)->all();
}

describe('kode lacak', function () {
    it('dibuat acak, panjang, bukan uuid, unik, dan tersembunyi dari serialisasi serta jejak audit', function () {
        [$p] = hlPermohonanDiKasubag();
        $staf = hlUser('staf-khusus');
        $s1 = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(), $staf);
        $s2 = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(['nomor_surat' => '56/UKM/2026']), $staf);

        $kode = [$p->kode_lacak, $s1->kode_lacak, $s2->kode_lacak];
        foreach ($kode as $k) {
            expect($k)->toMatch('/^[A-HJ-NP-Z2-9]{12}$/')->and(Str::isUuid($k))->toBeFalse();
        }
        expect(array_unique($kode))->toHaveCount(3)
            ->and(KodeLacak::tampil($p->kode_lacak))->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
            ->and($p->kode_lacak)->not->toContain($p->nomor)
            ->and(json_encode($p->toArray()))->not->toContain($p->kode_lacak)
            ->and(json_encode($s1->toArray()))->not->toContain($s1->kode_lacak)
            ->and(Aktivitas::all()->pluck('properties')->toJson())->not->toContain($p->kode_lacak)->not->toContain($s1->kode_lacak);
    });

    it('pembangkit tidak berurutan dan tidak mengulang kode yang sudah ada', function () {
        $dipakai = [];
        for ($i = 0; $i < 300; $i++) {
            $dipakai[KodeLacak::baru(fn ($k) => isset($dipakai[$k]))] = true;
        }
        expect($dipakai)->toHaveCount(300);

        $pertama = KodeLacak::baru(fn () => false);
        $panggilan = 0;
        $kedua = KodeLacak::baru(function ($k) use (&$panggilan) {
            return ++$panggilan <= 3; // tiga kode pertama dianggap bentrok
        });
        expect($panggilan)->toBe(4)->and($kedua)->not->toBe($pertama);

        expect(KodeLacak::normalisasi('k7m2-9qxd-4wpt'))->toBe('K7M29QXD4WPT')
            ->and(KodeLacak::normalisasi('K7M2 9QXD 4WPT'))->toBe('K7M29QXD4WPT')
            ->and(KodeLacak::normalisasi('K7M2-9QXD'))->toBeNull()
            ->and(KodeLacak::normalisasi('0OIL-1111-2222'))->toBeNull()
            ->and(KodeLacak::normalisasi(str_repeat('A', 500)))->toBeNull();
    });

    it('kolom unik di basis data', function () {
        [$p] = hlPermohonanDiKasubag();
        $staf = hlUser('staf-khusus');
        $s1 = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(), $staf);
        $s2 = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(['nomor_surat' => '77/X/2026']), $staf);

        expect(fn () => DB::table('surat_masuk')->where('id', $s2->id)->update(['kode_lacak' => $s1->kode_lacak]))->toThrow(QueryException::class);
        expect(fn () => DB::table('permohonan')->where('id', $p->id)->update(['kode_lacak' => null]))->not->toThrow(Throwable::class);
    });

    it('migrasi mengisi data lama yang belum berkode, unik, dan idempotent', function () {
        [$p] = hlPermohonanDiKasubag();
        $s = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(), hlUser('staf-khusus'));
        DB::table('permohonan')->update(['kode_lacak' => null]);
        DB::table('surat_masuk')->update(['kode_lacak' => null]);

        $migrasi = require database_path('migrations/2026_10_18_130000_add_kode_lacak_dan_hasil_ke_permohonan_dan_surat_masuk.php');
        $migrasi->up();

        $kodeP = DB::table('permohonan')->where('id', $p->id)->value('kode_lacak');
        $kodeS = DB::table('surat_masuk')->where('id', $s->id)->value('kode_lacak');
        expect($kodeP)->toMatch('/^[A-HJ-NP-Z2-9]{12}$/')->and($kodeS)->toMatch('/^[A-HJ-NP-Z2-9]{12}$/')->and($kodeP)->not->toBe($kodeS);

        $migrasi->up(); // ulang tanpa galat dan tanpa mengganti kode
        expect(DB::table('permohonan')->where('id', $p->id)->value('kode_lacak'))->toBe($kodeP)
            ->and(DB::table('surat_masuk')->where('id', $s->id)->value('kode_lacak'))->toBe($kodeS);
    });
});

describe('hasil cetak permohonan (jalur pembuatan -> selesai)', function () {
    it('petugas unit mengirim hasil: tautan tersimpan, status selesai, riwayat, dan pengaju diberi tahu beserta tautan lacak', function () {
        [$p, $unit, $petugas, $pengaju] = hlPermohonanPembuatan();
        expect($p->status)->toBe(StatusPermohonan::Pembuatan);

        $selesai = app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL, 'Sudah dicetak dan ditandatangani');
        $p = $p->fresh();

        $tautan = TautanBerkas::where('pemilik_id', $p->id)->where('jenis', 'hasil_cetak')->sole();
        expect($selesai->status)->toBe(StatusPermohonan::Selesai)->and($p->selesai_pada)->not->toBeNull()
            ->and($p->hasil_dikirim_pada)->not->toBeNull()->and($p->hasil_dikirim_oleh)->toBe($petugas->id)
            ->and($tautan->url)->toBe(HL_URL_HASIL)->and($tautan->ditambahkan_oleh)->toBe($petugas->id)
            ->and($p->riwayat->last()->ke_status)->toBe('selesai')->and($p->riwayat->last()->catatan)->toContain('Hasil dikirim kepada pengirim');

        $pesan = collect(hlNotifikasi($pengaju))->firstWhere('judul', 'Permohonan selesai: hasil tersedia');
        expect($pesan)->not->toBeNull()->and($pesan->ringkas)->toContain(KodeLacak::tampil($p->kode_lacak));
    });

    it('berlaku untuk jenis non-izin tanpa naskah (pengantar proposal)', function () {
        [$p, , $petugas] = hlPermohonanPembuatan('pengantar-proposal', ['berkas' => ['proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']]);

        app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL);

        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai)->and($p->fresh()->naskah_izin_id)->toBeNull();
    });

    it('mengonfirmasi ruangan yang ditahan dan mencatat pemakaiannya', function () {
        [$p, , $petugas] = hlPermohonanPembuatan('kegiatan-ruangan', ['ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']]]);
        expect($p->ruangan->first()->status)->toBe(PermohonanRuangan::DITAHAN);

        app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL);

        expect($p->ruangan()->first()->status)->toBe(PermohonanRuangan::DIKONFIRMASI);
        Queue::assertPushed(CatatPemakaianRuangan::class);
    });

    it('menolak ruangan yang kini dipakai pihak lain dan membiarkan status tetap pembuatan', function () {
        [$p, , $petugas] = hlPermohonanPembuatan('kegiatan-ruangan', ['ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']]]);
        PermohonanRuangan::where('permohonan_id', $p->id)->update(['status' => PermohonanRuangan::DITAHAN]);
        // Pemakaian lokal lain pada slot yang sama.
        PemakaianRuanganLokal::create(['ruangan_lokal_id' => RuanganLokal::firstWhere('kode', 'A101')->id, 'tanggal' => '2026-06-20', 'sesi' => 'pagi', 'keterangan' => 'Kuliah']);

        expect(fn () => app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL))->toThrow(ValidationException::class);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan)->and(TautanBerkas::where('jenis', 'hasil_cetak')->count())->toBe(0);
    });

    it('jalur izin lama tetap bekerja: naskah terbit otomatis menyelesaikan dan memberi tahu pengaju beserta tautan lacak', function () {
        [$p, $pengaju, $kasubag] = hlPermohonanDiKasubag();
        app(RekomendasiKasubag::class)->jalankan($p, $kasubag, null, null); // tanpa unit: jalur lama
        $p = $p->fresh();
        expect($p->status)->toBe(StatusPermohonan::Penerbitan);

        $admin = hlUser('admin-persuratan');
        $naskah = app(TerbitkanIzin::class)->jalankan($p, $admin);
        app(AjukanParaf::class)->jalankan($naskah, $naskah->penyusun, []);
        app(TandaTangani::class)->jalankan($naskah->fresh(), hlPejabat('dekan', 'dekan'));
        (new TerbitkanNaskah($naskah->fresh()))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));
        app(SelesaikanPermohonanDariNaskah::class)->handle(new NaskahTerbit($naskah->fresh()));

        $p = $p->fresh();
        expect($p->status)->toBe(StatusPermohonan::Selesai)->and($p->hasil_dikirim_pada)->not->toBeNull();
        $pesan = collect(hlNotifikasi($pengaju))->firstWhere('judul', 'Permohonan selesai: hasil tersedia');
        expect($pesan->ringkas)->toContain(KodeLacak::tampil($p->kode_lacak));

        // Halaman lacak mengarahkan ke verifikasi surat terbit, bukan tautan hasil.
        $this->get(hlLacak($p))->assertOk()->assertSee('Periksa keaslian surat yang terbit')->assertSee(route('verifikasi', $naskah->id), false);
    });

    it('otorisasi: hanya petugas unit yang ditugaskan (dan super-admin)', function () {
        [$p, $unit, $petugas, $pengaju] = hlPermohonanPembuatan();
        $petugasLain = hlUnit('rektorat');
        $kasubag = hlPejabat('kasubag', 'kasubag-umum');
        $super = hlUser('super-admin');

        foreach ([hlPetugas($petugasLain), $kasubag, $pengaju, hlUser('admin-persuratan'), hlUser('staf-khusus'), hlPejabat('dekan', 'dekan')] as $orang) {
            expect(fn () => app(KirimHasilPermohonan::class)->jalankan($p, $orang, HL_URL_HASIL))->toThrow(AuthorizationException::class);
        }
        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan);

        app(KirimHasilPermohonan::class)->jalankan($p, $super, HL_URL_HASIL);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai);
    });

    it('menolak status yang tidak memungkinkan dan klik ganda', function () {
        [$p, , $petugas] = hlPermohonanPembuatan();
        app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL);

        expect(fn () => app(KirimHasilPermohonan::class)->jalankan($p->fresh(), $petugas, HL_URL_HASIL))->toThrow(ValidationException::class);
        expect(TautanBerkas::where('jenis', 'hasil_cetak')->count())->toBe(1);

        [$p2, $unit2, $petugas2] = hlPermohonanPembuatan();
        $p2->forceFill(['status' => StatusPermohonan::Penerbitan])->saveQuietly();
        expect(fn () => app(KirimHasilPermohonan::class)->jalankan($p2, $petugas2, HL_URL_HASIL))->toThrow(ValidationException::class);
    });

    it('memvalidasi tautan hasil sesuai kebijakan berkas', function (string $url) {
        [$p, , $petugas] = hlPermohonanPembuatan();

        expect(fn () => app(KirimHasilPermohonan::class)->jalankan($p, $petugas, $url))->toThrow(ValidationException::class);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan)->and(TautanBerkas::where('jenis', 'hasil_cetak')->count())->toBe(0);
    })->with([
        'kosong' => [''],
        'bukan https' => ['http://drive.google.com/file/d/1HasilCetakAbCdEfGh/view'],
        'domain tak diizinkan' => ['https://situs-lain.example/hasil.pdf'],
        'pemendek url' => ['https://bit.ly/hasil'],
        'tautan folder' => ['https://drive.google.com/drive/folders/1FolderAbCdEfGh'],
        'berkredensial' => ['https://user:sandi@drive.google.com/file/d/1HasilCetakAbCdEfGh/view'],
    ]);
});

describe('hasil cetak surat masuk (prodi/eksternal)', function () {
    it('alur penuh: staf khusus -> dekan -> disposisi -> kasubag -> pembuat -> unggah hasil -> pemberitahuan -> selesai', function () {
        [$surat, $unit, $petugas, $kasubag, $staf] = hlSuratDitugaskan();

        // Penugasan lewat disposisi lanjutan: unit tercatat, petugas menerima, kasubag tetap berhak melapor.
        expect($surat->unit_pembuat_id)->toBe($unit->id)->and($surat->status)->toBe(StatusSuratMasuk::Didisposisikan)
            ->and(DisposisiPenerima::where('user_id', $petugas->id)->count())->toBe(1)
            ->and(Aktivitas::where('event', 'tugaskan-pembuat')->where('subject_id', $surat->id)->count())->toBe(1);

        Livewire::actingAs($petugas)->test(Tugas::class)
            ->assertSee($surat->nomor_agenda)->call('buka', 'surat:'.$surat->id)
            ->set('url', HL_URL_HASIL)->set('catatan', 'Surat balasan sudah ditandatangani')->call('kirimSurat', $surat->id)->assertHasNoErrors();

        $surat = $surat->fresh();
        $tautan = TautanBerkas::where('pemilik_id', $surat->id)->where('jenis', 'hasil_cetak')->sole();
        expect($surat->status)->toBe(StatusSuratMasuk::Selesai)->and($surat->hasil_dikirim_pada)->not->toBeNull()->and($surat->hasil_dikirim_oleh)->toBe($petugas->id)
            ->and($tautan->url)->toBe(HL_URL_HASIL)
            ->and(DisposisiPenerima::whereHas('disposisi', fn ($q) => $q->where('surat_masuk_id', $surat->id))->where('status', '!=', 'selesai')->count())->toBe(0)
            ->and(Aktivitas::where('event', 'hasil-dikirim')->where('subject_id', $surat->id)->count())->toBe(1);

        // Staf khusus (registrator) diberi tahu; pengirim eksternal lewat job (kontak tidak di muatan antrean).
        expect(collect(hlNotifikasi($staf))->pluck('judul'))->toContain('Hasil surat masuk dikirim kepada pengirim');
        Queue::assertPushed(KirimHasilKePengirim::class, fn ($job) => $job->suratMasukId === $surat->id);
        expect(serialize(new KirimHasilKePengirim($surat->id)))->not->toContain('rani@contoh.test')->not->toContain('081234567890');

        (new KirimHasilKePengirim($surat->id))->handle(app(GerbangWhatsapp::class));

        Mail::assertSent(HasilSuratKePengirim::class, function (HasilSuratKePengirim $m) use ($surat) {
            $teks = $m->render();

            return $m->hasTo('rani@contoh.test') && str_contains($teks, KodeLacak::tampil($surat->kode_lacak))
                && ! str_contains($teks, $surat->perihal) && ! str_contains($teks, 'RAHASIA-INTERNAL');
        });

        // Halaman lacak menampilkan tautan hasil.
        $this->get(hlLacak($surat))->assertOk()->assertSee('Selesai')->assertSee(HL_URL_HASIL, false)->assertSee('Hasil dikirim kepada pengirim');
    });

    it('prodi tanpa kontak: pimpinan unit pengirim diberi tahu', function () {
        $prodi = UnitKerja::where('aktif', true)->first() ?? UnitKerja::factory()->create();
        $kaprodi = User::factory()->create()->assignRole('pegawai');
        $jabatan = Jabatan::create(['kode' => 'kaprodi-uji', 'nama' => 'Ketua Program Studi Uji', 'unit_kerja_id' => $prodi->id, 'urutan' => 50]);
        PemangkuJabatan::create(['jabatan_id' => $jabatan->id, 'user_id' => $kaprodi->id, 'mulai' => '2026-01-01']);

        [$surat, , $petugas] = hlSuratDitugaskan(['jenis_pengirim' => 'prodi', 'pengirim_unit_kerja_id' => $prodi->id, 'pengirim_nama' => null, 'pengirim_instansi' => null, 'pengirim_surel' => null, 'pengirim_wa' => null]);
        app(KirimHasilSuratMasuk::class)->jalankan($surat, $petugas, HL_URL_HASIL);

        expect(collect(hlNotifikasi($kaprodi))->pluck('judul'))->toContain('Surat Anda telah selesai diproses');
        Queue::assertNotPushed(KirimHasilKePengirim::class);
    });

    it('kontak tidak bocor ke log, jejak audit, atau muatan antrean, termasuk saat pengiriman gagal', function () {
        [$surat, , $petugas] = hlSuratDitugaskan();
        app(KirimHasilSuratMasuk::class)->jalankan($surat, $petugas, HL_URL_HASIL);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('Gagal mengirim ke rani@contoh.test'));
        Log::shouldReceive('warning')->once()->withArgs(fn (string $m) => ! str_contains($m, 'rani@contoh.test') && ! str_contains($m, '081234567890'));
        (new KirimHasilKePengirim($surat->id))->handle(app(GerbangWhatsapp::class));

        expect(Aktivitas::all()->toJson())->not->toContain('rani@contoh.test')->not->toContain('081234567890')
            ->and(json_encode($surat->fresh()->toArray()))->not->toContain('rani@contoh.test');
    });

    it('hanya kasubag yang memegang disposisi yang dapat menugaskan; unit harus aktif dan bepetugas', function () {
        $staf = hlUser('staf-khusus');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(), $staf);
        app(TeruskanSuratKeDekan::class)->jalankan($surat, $staf);
        $dekan = hlPejabat('dekan', 'dekan');
        $kasubag = hlPejabat('kasubag', 'kasubag-umum');
        $wd = hlPejabat('wakil-dekan', 'wd-akademik');
        app(BuatDisposisi::class)->jalankan($surat->fresh(), $dekan, [$kasubag->id, $wd->id], ['tindak_lanjuti']);
        $indukKasubag = DisposisiPenerima::where('user_id', $kasubag->id)->firstOrFail();
        $indukWd = DisposisiPenerima::where('user_id', $wd->id)->firstOrFail();
        $unit = hlUnit('rektorat');

        // WD (bukan kasubag) dan dekan tidak berhak; disposisi orang lain tidak dapat dipakai.
        expect(fn () => app(TugaskanPembuatSuratMasuk::class)->jalankan($surat->fresh(), $wd, $indukWd, $unit->id))->toThrow(AuthorizationException::class);
        expect(fn () => app(TugaskanPembuatSuratMasuk::class)->jalankan($surat->fresh(), $kasubag, $indukWd, $unit->id))->toThrow(AuthorizationException::class);

        $nonaktif = hlUnit('nonaktif');
        $nonaktif->update(['aktif' => false]);
        expect(fn () => app(TugaskanPembuatSuratMasuk::class)->jalankan($surat->fresh(), $kasubag, $indukKasubag, $nonaktif->id))->toThrow(ValidationException::class);

        $kosong = UnitPembuat::create(['kode' => 'kosong', 'nama' => 'Kosong']);
        expect(fn () => app(TugaskanPembuatSuratMasuk::class)->jalankan($surat->fresh(), $kasubag, $indukKasubag, $kosong->id))->toThrow(ValidationException::class);
        expect($surat->fresh()->unit_pembuat_id)->toBeNull();

        app(TugaskanPembuatSuratMasuk::class)->jalankan($surat->fresh(), $kasubag, $indukKasubag, $unit->id);
        expect($surat->fresh()->unit_pembuat_id)->toBe($unit->id);
    });

    it('kotak masuk kasubag menugaskan pembuat', function () {
        $staf = hlUser('staf-khusus');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(), $staf);
        app(TeruskanSuratKeDekan::class)->jalankan($surat, $staf);
        $kasubag = hlPejabat('kasubag', 'kasubag-umum');
        app(BuatDisposisi::class)->jalankan($surat->fresh(), hlPejabat('dekan', 'dekan'), [$kasubag->id], ['tindak_lanjuti']);
        $unit = hlUnit('rektorat');
        $induk = DisposisiPenerima::where('user_id', $kasubag->id)->firstOrFail();

        Livewire::actingAs($kasubag)->test(KotakMasuk::class)->call('buka', 'penerima:'.$induk->id)
            ->assertSee('Tugaskan pembuat surat')->call('mulai', 'pembuat')->assertSee('Unit Rektorat')
            ->set('unitPembuatId', $unit->id)->call('tugaskanPembuat', $induk->id)->assertHasNoErrors();

        expect($surat->fresh()->unit_pembuat_id)->toBe($unit->id);
    });

    it('otorisasi kirim hasil: petugas unit lain, kasubag, staf khusus, dekan ditolak', function () {
        [$surat, $unit, $petugas, $kasubag, $staf] = hlSuratDitugaskan();
        $lain = hlPetugas(hlUnit('rektorat'));

        foreach ([$lain, $kasubag, $staf, hlPejabat('dekan', 'dekan'), hlUser('admin-persuratan')] as $orang) {
            expect(fn () => app(KirimHasilSuratMasuk::class)->jalankan($surat, $orang, HL_URL_HASIL))->toThrow(AuthorizationException::class);
        }
        expect($surat->fresh()->status)->toBe(StatusSuratMasuk::Didisposisikan);

        app(KirimHasilSuratMasuk::class)->jalankan($surat, $petugas, HL_URL_HASIL);
        expect(fn () => app(KirimHasilSuratMasuk::class)->jalankan($surat->fresh(), $petugas, HL_URL_HASIL))->toThrow(AuthorizationException::class);
    });

    it('memvalidasi tautan hasil surat masuk', function (string $url) {
        [$surat, , $petugas] = hlSuratDitugaskan();

        expect(fn () => app(KirimHasilSuratMasuk::class)->jalankan($surat, $petugas, $url))->toThrow(ValidationException::class);
        expect($surat->fresh()->status)->toBe(StatusSuratMasuk::Didisposisikan);
    })->with([
        'kosong' => [''], 'http' => ['http://drive.google.com/file/d/1HasilCetakAbCdEfGh/view'], 'domain lain' => ['https://situs-lain.example/a.pdf'],
        'pemendek' => ['https://s.id/abc'], 'folder' => ['https://drive.google.com/drive/folders/1FolderAbCdEfGh'],
    ]);
});

describe('petugas unit tanpa akses panel', function () {
    it('pegawai tidak masuk panel admin tetapi memakai halaman Tugas pembuat surat', function () {
        [$surat, , $petugas] = hlSuratDitugaskan();

        expect($petugas->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
        $this->actingAs($petugas)->get('/pembuat')->assertOk()->assertSee('Tugas pembuat surat')->assertSee($surat->nomor_agenda);
        $this->actingAs($petugas)->get('/disposisi')->assertOk()->assertSee('Tugas pembuat'); // tautan navigasi
    });

    it('pengguna yang bukan petugas unit mendapat 403; petugas hanya melihat tugas unitnya', function () {
        [$surat, , $petugas] = hlSuratDitugaskan();
        $bukan = User::factory()->create()->assignRole('pegawai');
        $lain = hlPetugas(hlUnit('rektorat'));

        $this->actingAs($bukan)->get('/pembuat')->assertForbidden();
        $this->actingAs($lain)->get('/pembuat')->assertOk()->assertDontSee($surat->nomor_agenda);
        $this->actingAs($petugas)->get('/pembuat')->assertOk()->assertSee($surat->nomor_agenda);
        auth()->logout();
        $this->get('/pembuat')->assertRedirect(); // tamu dialihkan
    });

    it('menampilkan dan menyelesaikan permohonan non-izin dari halaman tugas; galat validasi tampil', function () {
        [$p, , $petugas] = hlPermohonanPembuatan();

        Livewire::actingAs($petugas)->test(Tugas::class)->assertSee($p->nomor)
            ->call('buka', 'permohonan:'.$p->id)->set('url', 'https://bit.ly/hasil')->call('kirimPermohonan', $p->id)->assertHasErrors('url');
        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan);

        Livewire::actingAs($petugas)->test(Tugas::class)
            ->call('buka', 'permohonan:'.$p->id)->set('url', HL_URL_HASIL)->call('kirimPermohonan', $p->id)->assertHasNoErrors();
        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai);
    });

    it('petugas lain tidak dapat memakai id permohonan unit lain lewat halaman tugas', function () {
        [$p] = hlPermohonanPembuatan();
        $lain = hlPetugas(hlUnit('rektorat'));

        Livewire::actingAs($lain)->test(Tugas::class)->assertDontSee($p->nomor)->set('url', HL_URL_HASIL)->call('kirimPermohonan', $p->id);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan);
    });

    it('panel Filament: petugas ber-peran panel mengirim hasil dari rincian permohonan', function () {
        [$p, $unit] = hlPermohonanPembuatan();
        $petugas = hlUser('operator-layanan');
        $unit->petugas()->attach($petugas->id);

        Livewire::actingAs($petugas)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionVisible('kirimHasil')
            ->callAction('kirimHasil', ['url' => HL_URL_HASIL, 'catatan' => null])->assertNotified();
        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai);
    });
});

describe('perbaikan panel permohonan untuk staf khusus', function () {
    it('staf khusus (permohonan.lihat) melihat daftar permohonan; pengguna tanpa izin tidak', function () {
        [$p] = hlPermohonanDiKasubag();
        $staf = hlUser('staf-khusus');

        Livewire::actingAs($staf)->test(ListPermohonans::class)->assertCanSeeTableRecords([$p]);
        $this->actingAs($staf)->get('/admin/permohonan')->assertOk();
        $this->actingAs(hlUser('operator-layanan'))->get('/admin/permohonan')->assertForbidden();
    });

    it('tautan lacak publik tampil di rincian permohonan dan surat masuk untuk staf, bukan untuk petugas biasa', function () {
        [$p, $unit] = hlPermohonanPembuatan();
        $staf = hlUser('staf-khusus');

        Livewire::actingAs($staf)->test(ViewPermohonan::class, ['record' => $p->getKey()])->assertSee(KodeLacak::tampil($p->kode_lacak));

        $petugasPanel = hlUser('operator-layanan');
        $unit->petugas()->attach($petugasPanel->id);
        Livewire::actingAs($petugasPanel)->test(ViewPermohonan::class, ['record' => $p->getKey()])->assertDontSee(KodeLacak::tampil($p->kode_lacak));

        $surat = app(RegistrasiSuratMasuk::class)->jalankan(hlDataSurat(), $staf);
        Livewire::actingAs($staf)->test(EditSuratMasuk::class, ['record' => $surat->getKey()])->assertSee(KodeLacak::tampil($surat->kode_lacak));
    });
});

describe('halaman lacak publik', function () {
    it('menampilkan permohonan: jenis, perihal ringkas, tahap dan tanggal, jabatan, status, dan hasil; tanpa tautan internal', function () {
        [$p, , $petugas] = hlPermohonanPembuatan();
        app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL, 'Catatan hasil internal');

        $r = $this->get(hlLacak($p->fresh()))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('noindex, nofollow', false)
            ->assertSee($p->jenis->nama)->assertSee('Izin seminar')->assertSee('Selesai')->assertSee('Hasil dikirim kepada pengirim')
            ->assertSee('Dekan')->assertSee('Wakil Dekan Bidang Akademik')->assertSee('Kepala Subbagian Umum')
            ->assertSee('Buka hasil')->assertSee(HL_URL_HASIL, false)->assertSee('01 Jun 2026');
        expect($r->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('tidak membocorkan catatan internal, instruksi disposisi, nama pribadi, kontak, atau isi surat', function () {
        [$p, , $petugas] = hlPermohonanPembuatan();
        app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL, 'Catatan hasil internal');
        $isi = $this->get(hlLacak($p->fresh()))->assertOk()->getContent();

        foreach (['RAHASIA-INTERNAL', 'Catatan internal dekan', 'Catatan internal WD', 'Catatan hasil internal', 'Budi Santoso', '081200099988', 'Deskripsi', 'Tindak lanjuti', 'tindak_lanjuti'] as $terlarang) {
            expect($isi)->not->toContain($terlarang);
        }
        foreach (User::pluck('name') as $nama) {
            expect($isi)->not->toContain($nama);
        }
        expect($isi)->not->toContain($p->id)->not->toContain($petugas->id);

        $surat = null;
        [$surat, , $petugasSurat] = hlSuratDitugaskan();
        app(KirimHasilSuratMasuk::class)->jalankan($surat, $petugasSurat, HL_URL_HASIL, 'Catatan hasil internal');
        $isi = $this->get(hlLacak($surat->fresh()))->assertOk()->getContent();

        foreach (['RAHASIA-INTERNAL', 'Catatan hasil internal', 'rani@contoh.test', '081234567890', 'Rani Putri', 'UKM Pecinta Alam Kota', '55/UKM/2026', 'Instruksi internal', 'Unit Surat Tugas Dosen'] as $terlarang) {
            if ($terlarang === 'Unit Surat Tugas Dosen') {
                continue; // nama unit organisasi boleh tampil pada tahap penugasan
            }
            expect($isi)->not->toContain($terlarang);
        }
        foreach (User::pluck('name') as $nama) {
            expect($isi)->not->toContain($nama);
        }
        expect($isi)->not->toContain($surat->id);
    });

    it('kode salah, tak berformat, dan tak ada menghasilkan jawaban identik tanpa membocorkan keberadaan', function () {
        [$p] = hlPermohonanDiKasubag();

        $tidakAda = $this->get('/lacak/AAAA-BBBB-CCCC');
        $takSah = $this->get('/lacak/bukan-kode');
        $uuid = $this->get('/lacak/'.$p->id);
        $form = $this->get('/lacak?kode=xyz');

        foreach ([$tidakAda, $takSah, $uuid, $form] as $r) {
            $r->assertNotFound()->assertSee('Kode lacak tidak ditemukan')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        expect($takSah->getContent())->toBe($tidakAda->getContent())->and($uuid->getContent())->toBe($tidakAda->getContent())->and($form->getContent())->toBe($tidakAda->getContent());
        expect($tidakAda->getContent())->not->toContain($p->nomor);
    });

    it('formulir mengalihkan kode berformat sah (huruf kecil/tanpa tanda hubung) ke halaman lacak', function () {
        [$p] = hlPermohonanDiKasubag();

        $this->get('/lacak')->assertOk()->assertSee('Lacak surat');
        $this->get('/lacak?kode='.strtolower($p->kode_lacak))->assertRedirect('/lacak/'.KodeLacak::tampil($p->kode_lacak));
        $this->followingRedirects()->get('/lacak?kode='.strtolower($p->kode_lacak))->assertOk()->assertSee($p->jenis->nama);
        $this->get('/lacak/'.strtolower($p->kode_lacak))->assertOk();
    });

    it('surat rahasia: hanya tahap generik, tanpa perihal, jabatan, unit, maupun tautan hasil (BR-04)', function () {
        [$surat, , $petugas] = hlSuratDitugaskan(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal sangat sensitif SENSITIF-XYZ']);

        $sebelum = $this->get(hlLacak($surat))->assertOk()->assertDontSee('SENSITIF-XYZ')->assertSee('Perihal tidak ditampilkan')->assertSee('Dalam proses')
            ->assertDontSee('Kepala Subbagian')->assertDontSee('Staf khusus')->assertDontSee('Unit Surat Tugas Dosen')->assertDontSee('Ditugaskan')->getContent();
        expect($sebelum)->not->toContain('Pejabat');

        app(KirimHasilSuratMasuk::class)->jalankan($surat, $petugas, HL_URL_HASIL);
        $this->get(hlLacak($surat->fresh()))->assertOk()->assertDontSee(HL_URL_HASIL, false)->assertDontSee('SENSITIF-XYZ')->assertSee('Hubungi fakultas');

        // Kanal lain pun tidak membawa perihal rahasia: surel pengirim hanya memuat tautan lacak.
        (new KirimHasilKePengirim($surat->id))->handle(app(GerbangWhatsapp::class));
        Mail::assertSent(HasilSuratKePengirim::class, fn ($m) => ! str_contains($m->render(), 'SENSITIF-XYZ'));
    });

    it('menampilkan tahap berurutan dengan pelaku tingkat jabatan untuk surat masuk', function () {
        [$surat, , $petugas] = hlSuratDitugaskan();

        $this->get(hlLacak($surat))->assertOk()->assertSeeInOrder(['Surat diterima dan didaftarkan', 'Diteruskan kepada dekan', 'Didisposisikan', 'Ditugaskan kepada Unit Surat Tugas Dosen'])
            ->assertSee('Staf khusus dekanat')->assertSee('Kepala Subbagian Umum')->assertSee('Surat sedang dibuat');
    });

    it('dibatasi laju (throttle) per IP', function () {
        for ($i = 0; $i < 20; $i++) {
            $this->get('/lacak/AAAA-BBBB-CCCC')->assertNotFound();
        }

        $this->get('/lacak/AAAA-BBBB-CCCC')->assertStatus(429);
        $this->get('/lacak')->assertStatus(429);
    });

    it('tanpa login, memakai header halaman publik (CSP) dan tidak memuat tautan ke area internal', function () {
        [$p] = hlPermohonanDiKasubag();

        $r = $this->get(hlLacak($p))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Security-Policy');
        expect($r->getContent())->not->toContain('/admin')->not->toContain('/ormawa/');
    });
});

describe('linimasa untuk pengirim ber-akun (Progres)', function () {
    it('memakai linimasa terpadu: jabatan, bukan nama pribadi; catatan internal tersembunyi; hasil dan tautan lacak tampil', function () {
        [$p, $unit, $petugas, $pengaju] = hlPermohonanPembuatan();
        app(KirimHasilPermohonan::class)->jalankan($p, $petugas, HL_URL_HASIL, 'Catatan hasil internal');
        $p = $p->fresh();

        Livewire::actingAs($pengaju)->test(Progres::class, ['ormawa' => $p->ormawa_id])
            ->call('pilih', $p->id)
            ->assertSee('Kepala Subbagian Umum')->assertSee('Wakil Dekan Bidang Akademik')->assertSee('Surat sedang dibuat')->assertSee('Hasil dikirim kepada pengirim')
            ->assertSee(KodeLacak::tampil($p->kode_lacak))->assertSee('Hasil cetak')
            ->assertDontSee('Catatan internal')->assertDontSee('RAHASIA-INTERNAL')->assertDontSee('Admin Rahasia Pribadi')->assertDontSee($petugas->name);
    });

    it('alasan pengembalian/penolakan yang ditujukan kepada pengirim tetap tampil', function () {
        [$p, , $kasubag] = hlPermohonanDiKasubag();
        $pengaju = $p->pengaju;
        app(RekomendasiKasubag::class)->tolak($p, $kasubag, 'Tidak sesuai kalender akademik');

        Livewire::actingAs($pengaju)->test(Progres::class, ['ormawa' => $p->ormawa_id])->call('pilih', $p->id)
            ->assertSee('Ditolak')->assertSee('Tidak sesuai kalender akademik');
    });
});
