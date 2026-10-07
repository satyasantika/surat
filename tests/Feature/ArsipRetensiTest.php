<?php

use App\Actions\Masuk\ArsipkanSuratMasuk;
use App\Filament\Pages\Arsip;
use App\Filament\Resources\SuratMasuks\Pages\ListSuratMasuks;
use App\Models\Aktivitas;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Services\Arsip\Retensi;
use App\Services\Laporan\DaftarLaporan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function arsipAdmin(string $peran = 'admin-persuratan'): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function arsipKlasifikasi(string $kode, ?int $aktif, ?int $inaktif, ?string $akhir = 'musnah'): KlasifikasiArsip
{
    return KlasifikasiArsip::create(['kode' => $kode, 'nama' => "Klasifikasi {$kode}", 'retensi_aktif_tahun' => $aktif, 'retensi_inaktif_tahun' => $inaktif, 'keterangan_akhir' => $akhir]);
}

function arsipSurat(User $admin, string $agenda, string $status, array $ubah = []): SuratMasuk
{
    $s = (new SuratMasuk(['nomor_surat' => "S/{$agenda}", 'tanggal_surat' => '2020-01-10', 'asal' => 'Dinas Asal', 'perihal' => 'Perihal '.$agenda, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa']))
        ->forceFill(['nomor_agenda' => $agenda, 'tanggal_terima' => '2020-01-12 09:00:00', 'status' => $status, 'diregistrasi_oleh' => $admin->id, ...$ubah]);
    $s->save();

    return $s;
}

function arsipNaskah(User $admin, string $nomor, string $status, string $tanggal, array $ubah = [], array $tujuan = []): Naskah
{
    $n = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'perihal' => 'Perihal '.$nomor, 'data' => [], 'status' => $status, 'penyusun_id' => $admin->id,
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', ...$ubah]);
    $n->forceFill(['nomor' => $nomor, 'tanggal_naskah' => $tanggal])->save();

    foreach ($tujuan as $i => $nama) {
        $n->tujuan()->create(['jenis' => 'tujuan', 'nama' => $nama, 'urutan' => $i + 1]);
    }

    return $n;
}

describe('mengarsipkan surat masuk', function () {
    it('admin mengarsipkan surat selesai dan tercatat di audit', function () {
        $admin = arsipAdmin();
        $s = arsipSurat($admin, '0001/AGD/2026', 'selesai');

        $hasil = app(ArsipkanSuratMasuk::class)->jalankan($s, $admin);

        expect($hasil->status->value)->toBe('diarsipkan')->and($hasil->diarsipkan_pada->toDateString())->toBe('2026-06-01')->and($hasil->diarsipkan_oleh)->toBe($admin->id)
            ->and(Aktivitas::where('log_name', 'surat-masuk')->where('event', 'arsipkan')->count())->toBe(1);
    });

    it('hanya dari status selesai, hanya oleh admin; surat rahasia tetap dapat diarsipkan (metadata)', function () {
        $admin = arsipAdmin();

        foreach (['diterima', 'didisposisikan', 'diarsipkan'] as $status) {
            expect(fn () => app(ArsipkanSuratMasuk::class)->jalankan(arsipSurat($admin, "A-{$status}", $status), $admin))->toThrow(ValidationException::class);
        }

        $s = arsipSurat($admin, 'B-1', 'selesai');
        expect(fn () => app(ArsipkanSuratMasuk::class)->jalankan($s, arsipAdmin('pegawai')))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ArsipkanSuratMasuk::class)->jalankan($s, arsipAdmin('pengurus-ormawa')))->toThrow(AuthorizationException::class);

        $rahasia = arsipSurat($admin, 'R-1', 'selesai', ['klasifikasi_keamanan' => 'rahasia']);
        expect(app(ArsipkanSuratMasuk::class)->jalankan($rahasia, $admin)->status->value)->toBe('diarsipkan');
    });

    it('aksi Arsipkan di panel muncul hanya untuk surat selesai', function () {
        $admin = arsipAdmin();
        $selesai = arsipSurat($admin, 'P-1', 'selesai');
        $baru = arsipSurat($admin, 'P-2', 'diterima');

        $t = Livewire::actingAs($admin)->test(ListSuratMasuks::class)->assertTableActionVisible('arsipkan', $selesai)->assertTableActionHidden('arsipkan', $baru);
        $t->callTableAction('arsipkan', $selesai)->assertNotified();

        expect($selesai->fresh()->status->value)->toBe('diarsipkan');
    });
});

describe('peninjauan retensi', function () {
    it('menandai arsip yang melewati retensi aktif dan inaktif sesuai klasifikasi', function () {
        $admin = arsipAdmin();
        $k = arsipKlasifikasi('KP.01', 2, 3, 'musnah');
        $permanen = arsipKlasifikasi('KP.02', 1, 2, 'permanen');
        $tanpa = arsipKlasifikasi('KP.03', null, null);
        $tanpaInaktif = arsipKlasifikasi('KP.04', 2, null, null);

        arsipSurat($admin, 'I-1', 'diarsipkan', ['klasifikasi_arsip_id' => $k->id, 'diarsipkan_pada' => '2020-01-15 08:00:00']);
        arsipSurat($admin, 'I-2', 'diarsipkan', ['klasifikasi_arsip_id' => $k->id, 'diarsipkan_pada' => '2023-03-01 08:00:00']);
        arsipSurat($admin, 'I-3', 'diarsipkan', ['klasifikasi_arsip_id' => $k->id, 'diarsipkan_pada' => '2025-12-01 08:00:00']);
        arsipSurat($admin, 'I-4', 'selesai', ['klasifikasi_arsip_id' => $k->id, 'tanggal_terima' => '2015-01-01 08:00:00']);
        arsipSurat($admin, 'I-5', 'diarsipkan', ['klasifikasi_arsip_id' => $tanpa->id, 'diarsipkan_pada' => '2010-01-01 08:00:00']);
        arsipSurat($admin, 'I-6', 'diarsipkan', ['klasifikasi_arsip_id' => $tanpaInaktif->id, 'diarsipkan_pada' => '2020-06-01 08:00:00']);
        arsipSurat($admin, 'I-7', 'diarsipkan', ['diarsipkan_pada' => '2010-01-01 08:00:00']);
        arsipNaskah($admin, 'N-1/2018', 'terbit', '2018-04-01', ['klasifikasi_arsip_id' => $permanen->id]);
        arsipNaskah($admin, 'N-2/2018', 'draf', '2018-04-01', ['klasifikasi_arsip_id' => $permanen->id]);
        arsipNaskah($admin, 'N-3/2018', 'dibatalkan', '2018-04-01', ['klasifikasi_arsip_id' => $permanen->id]);

        $hasil = collect(Retensi::tinjau())->keyBy('nomor');

        expect($hasil->keys()->sort()->values()->all())->toBe(['I-1', 'I-2', 'I-6', 'N-1/2018'])
            ->and($hasil['I-1'])->toMatchArray(['tahap' => Retensi::INAKTIF_LEWAT, 'batas_aktif' => '2022-01-15', 'batas_inaktif' => '2025-01-15', 'nasib_akhir' => 'Musnah', 'jenis' => 'Surat masuk'])
            ->and($hasil['I-2'])->toMatchArray(['tahap' => Retensi::AKTIF_LEWAT, 'batas_aktif' => '2025-03-01', 'batas_inaktif' => '2028-03-01', 'nasib_akhir' => null])
            ->and($hasil['I-6'])->toMatchArray(['tahap' => Retensi::AKTIF_LEWAT, 'batas_inaktif' => null])
            ->and($hasil['N-1/2018'])->toMatchArray(['tahap' => Retensi::INAKTIF_LEWAT, 'nasib_akhir' => 'Permanen', 'jenis' => 'Naskah keluar']);
    });

    it('tepat pada batas dihitung lewat; sehari sebelumnya belum', function () {
        $admin = arsipAdmin();
        $k = arsipKlasifikasi('KP.05', 2, 1);
        arsipSurat($admin, 'T-1', 'diarsipkan', ['klasifikasi_arsip_id' => $k->id, 'diarsipkan_pada' => '2024-06-01 23:00:00']);

        expect(Retensi::tinjau(CarbonImmutable::parse('2026-05-31')))->toBe([])
            ->and(Retensi::tinjau(CarbonImmutable::parse('2026-06-01'))[0]['tahap'])->toBe(Retensi::AKTIF_LEWAT)
            ->and(Retensi::tinjau(CarbonImmutable::parse('2027-06-01'))[0]['tahap'])->toBe(Retensi::INAKTIF_LEWAT);
    });

    it('LAP-06 memuat hasil peninjauan dan terdaftar bersama laporan lain', function () {
        $admin = arsipAdmin();
        arsipSurat($admin, 'L-1', 'diarsipkan', ['klasifikasi_arsip_id' => arsipKlasifikasi('KP.06', 1, 1)->id, 'diarsipkan_pada' => '2020-01-01 08:00:00']);

        $t = DaftarLaporan::cari('lap-06')->susun(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-06-30'));

        expect(array_keys(DaftarLaporan::semua()))->toBe(['lap-01', 'lap-02', 'lap-03', 'lap-04', 'lap-05', 'lap-06'])
            ->and($t[0]['baris'])->toBe([['KP.06 Klasifikasi KP.06', 'Surat masuk', 'L-1', '2020-01-01', '2021-01-01', '2022-01-01', 'Melewati retensi inaktif', 'Musnah']]);
    });
});

describe('surat:laporan-retensi', function () {
    it('memberi tahu admin sekali per bulan dan tidak menghapus apa pun', function () {
        Notification::fake();
        $admin = arsipAdmin();
        $dekan = arsipAdmin('dekan');
        $k = arsipKlasifikasi('KP.07', 1, 1);
        $s = arsipSurat($admin, 'C-1', 'diarsipkan', ['klasifikasi_arsip_id' => $k->id, 'diarsipkan_pada' => '2020-01-01 08:00:00']);
        arsipSurat($admin, 'C-2', 'diarsipkan', ['klasifikasi_arsip_id' => $k->id, 'diarsipkan_pada' => '2025-02-01 08:00:00']);

        $this->artisan('surat:laporan-retensi')->expectsOutputToContain('2 arsip melewati retensi (1 masuk masa inaktif, 1 melewati retensi inaktif)')->assertSuccessful();

        $n = Notification::sent($admin, NotifikasiTahap::class);
        expect($n)->toHaveCount(1)->and($n->first()->judul)->toBe('Arsip melewati retensi')->and($n->first()->ringkas)->toContain('2 arsip')->toContain('prosedur resmi')
            ->and(Notification::sent($dekan, NotifikasiTahap::class))->toHaveCount(0)
            ->and(SuratMasuk::count())->toBe(2)->and($s->fresh())->not->toBeNull();

        $this->artisan('surat:laporan-retensi');
        expect(Notification::sent($admin, NotifikasiTahap::class))->toHaveCount(1);

        $this->artisan('surat:laporan-retensi', ['--ulang' => true]);
        expect(Notification::sent($admin, NotifikasiTahap::class))->toHaveCount(2);

        CarbonImmutable::setTestNow('2026-07-01 09:00:00');
        $this->artisan('surat:laporan-retensi');
        expect(Notification::sent($admin, NotifikasiTahap::class))->toHaveCount(3);
    });

    it('tanpa arsip lewat retensi tidak mengirim notifikasi; terjadwal bulanan', function () {
        Notification::fake();
        $admin = arsipAdmin();

        $this->artisan('surat:laporan-retensi')->expectsOutputToContain('0 arsip melewati retensi')->assertSuccessful();
        Notification::assertNothingSent();

        $e = collect(app(Schedule::class)->events())->first(fn ($ev) => str_contains((string) $ev->command, 'surat:laporan-retensi'));
        expect($e->expression)->toBe('30 6 1 * *')->and($e->withoutOverlapping)->toBeTrue()->and($e->onOneServer)->toBeTrue();
    });
});

describe('halaman Arsip', function () {
    it('dapat dicari menurut nomor, asal, tujuan, klasifikasi, periode, dan jenis', function () {
        $admin = arsipAdmin();
        $k = arsipKlasifikasi('KP.10', 2, 3);
        $k2 = arsipKlasifikasi('KP.20', 2, 3);
        arsipSurat($admin, '0101/AGD/2026', 'diarsipkan', ['asal' => 'Dinas Pendidikan Kota', 'klasifikasi_arsip_id' => $k->id]);
        arsipSurat($admin, '0102/AGD/2026', 'selesai', ['asal' => 'Kementerian', 'klasifikasi_arsip_id' => $k2->id, 'tanggal_terima' => '2026-02-10 09:00:00']);
        arsipNaskah($admin, '55/UN58.10/KM.03.02/2026', 'terbit', '2026-03-15', ['klasifikasi_arsip_id' => $k->id], ['Bupati Tasikmalaya']);

        $t = Livewire::actingAs($admin)->test(Arsip::class);

        $t->assertSee('0101/AGD/2026')->assertSee('0102/AGD/2026')->assertSee('55/UN58.10/KM.03.02/2026');
        $t->set('kata', 'Dinas Pendidikan')->assertSee('0101/AGD/2026')->assertDontSee('0102/AGD/2026')->assertDontSee('55/UN58.10');
        $t->set('kata', 'Bupati')->assertSee('55/UN58.10/KM.03.02/2026')->assertDontSee('0101/AGD/2026');
        $t->set('kata', '0102')->assertSee('0102/AGD/2026')->assertDontSee('0101/AGD/2026');
        $t->set('kata', '')->set('klasifikasi', $k2->id)->assertSee('0102/AGD/2026')->assertDontSee('0101/AGD/2026')->assertDontSee('55/UN58.10');
        $t->set('klasifikasi', '')->set('jenis', 'keluar')->assertSee('55/UN58.10')->assertDontSee('0101/AGD/2026');
        $t->set('jenis', 'masuk')->set('dari', '2026-02-01')->set('sampai', '2026-02-28')->assertSee('0102/AGD/2026')->assertDontSee('0101/AGD/2026');
    });

    it('menghormati klasifikasi keamanan: perihal tertutup tak tampil dan tak dapat dicari', function () {
        $admin = arsipAdmin();
        arsipSurat($admin, '0201/AGD/2026', 'selesai', ['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'RAHASIA-ARSIP-ZZZ', 'tanggal_terima' => '2026-02-10 09:00:00']);
        arsipSurat($admin, '0202/AGD/2026', 'selesai', ['perihal' => 'Perihal Terang', 'tanggal_terima' => '2026-02-11 09:00:00']);
        arsipNaskah($admin, '77/UN58.10/KM.03.02/2026', 'terbit', '2026-03-15', ['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'NASKAH-RAHASIA-QQQ']);

        $t = Livewire::actingAs($admin)->test(Arsip::class);
        $t->assertSee('0201/AGD/2026')->assertSee(SuratMasuk::TOPENGAN)->assertSee('Perihal Terang')->assertDontSee('RAHASIA-ARSIP-ZZZ');

        $t->set('kata', 'RAHASIA-ARSIP')->assertDontSee('0201/AGD/2026');
        $t->set('kata', 'NASKAH-RAHASIA')->assertDontSee('77/UN58.10');
        $t->set('kata', 'Terang')->assertSee('0202/AGD/2026');

        // pejabat lain berizin arsip: naskah rahasia dibatasi, tidak tampil dan tidak dapat dicari lewat perihal
        $k = Livewire::actingAs(arsipAdmin('kasubag'))->test(Arsip::class);
        $k->assertSee('77/UN58.10/KM.03.02/2026')->assertSee('[DIBATASI]')->assertDontSee('NASKAH-RAHASIA-QQQ')->assertDontSee('0201/AGD/2026');
        $k->set('kata', 'NASKAH-RAHASIA')->assertDontSee('77/UN58.10');
    });

    it('hanya pemegang izin arsip.lihat', function () {
        foreach (['pegawai', 'pengurus-ormawa', 'pembina-ormawa'] as $peran) {
            $this->actingAs(arsipAdmin($peran))->get('/admin/arsip')->assertForbidden();
        }

        $this->actingAs(arsipAdmin('kasubag'))->get('/admin/arsip')->assertOk();
        $this->actingAs(arsipAdmin())->get('/admin/arsip')->assertOk();
    });
});
