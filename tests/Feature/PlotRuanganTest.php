<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Filament\Pages\PlotRuangan;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PemakaianRuanganLokal;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RuanganLokal;
use App\Models\User;
use App\Services\Ruangan\LayananRuanganLokal;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
    RuanganLokal::create(['kode' => 'B202', 'nama' => 'Ruang B202']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function penataRuang(string $peran = 'operator-layanan'): User
{
    $u = User::factory()->create()->assignRole($peran);
    $peran === 'operator-layanan' && $u->givePermissionTo('ruangan.kelola-jadwal');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function tahanRuangan(string $kode = 'A101', string $tanggal = '2026-06-20', string $sesi = 'pagi', string $nama = 'Seminar PLOT'): Permohonan
{
    $o = Ormawa::create(['nama' => 'HIMA '.random_int(1, 99999), 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 1999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    return app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', 'kegiatan-ruangan'), [
        'nama_kegiatan' => $nama, 'perihal' => 'Izin', 'tanggal_mulai' => $tanggal, 'tanggal_selesai' => $tanggal, 'deskripsi' => 'D',
        'penanggung_jawab' => ['ketua' => ['nama' => 'B']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        'ruangan' => [['kode' => $kode, 'tanggal' => $tanggal, 'sesi' => $sesi]],
    ]);
}

it('membatasi halaman ke pemegang ruangan.kelola-jadwal', function () {
    $this->actingAs(penataRuang())->get('/admin/plot-ruangan')->assertOk();
    $this->actingAs(penataRuang('admin-persuratan'))->get('/admin/plot-ruangan')->assertOk();

    $biasa = User::factory()->create()->assignRole('operator-layanan');
    $biasa->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($biasa)->get('/admin/plot-ruangan')->assertForbidden();
    $this->actingAs(penataRuang('kasubag'))->get('/admin/plot-ruangan')->assertForbidden();
});

it('menampilkan kalender bulan berjalan dengan sesi yang ditahan, dikonfirmasi, dan dipakai', function () {
    tahanRuangan('A101', '2026-06-20', 'pagi');
    $konfirmasi = tahanRuangan('A101', '2026-06-21', 'siang');
    $konfirmasi->ruangan()->update(['status' => PermohonanRuangan::DIKONFIRMASI]);
    (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-22', 'seharian', null, 'Ujian akhir');

    $data = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->assertSet('bulan', '2026-06')->instance()->dataKalender();
    $hari = collect($data['minggu'])->flatten(1)->filter()->keyBy('tanggal');

    expect($data['galat'])->toBeFalse()->and(collect($data['ruangan'])->pluck('kode')->all())->toBe(['A101', 'B202'])
        ->and($hari['2026-06-20']['entri'][0])->toMatchArray(['sesi' => 'pagi', 'jenis' => 'ditahan'])
        ->and($hari['2026-06-21']['entri'][0])->toMatchArray(['sesi' => 'siang', 'jenis' => 'dikonfirmasi'])
        ->and($hari['2026-06-22']['entri'][0])->toMatchArray(['sesi' => 'seharian', 'jenis' => 'lain', 'label' => 'Ujian akhir'])
        ->and($hari['2026-06-23']['entri'])->toBe([])->and($hari)->toHaveCount(30);
});

it('menyusun minggu mulai Senin dengan sel kosong di awal dan akhir', function () {
    $data = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->instance()->dataKalender();

    // 1 Juni 2026 = Senin; 30 Juni = Selasa
    expect($data['minggu'][0][0]['tanggal'])->toBe('2026-06-01')->and(collect($data['minggu'])->every(fn ($m) => count($m) === 7))->toBeTrue()
        ->and(last($data['minggu'])[1]['tanggal'])->toBe('2026-06-30')->and(last($data['minggu'])[2])->toBeNull();
});

it('hanya menampilkan ruangan terpilih dan berpindah bulan', function () {
    tahanRuangan('A101', '2026-06-20', 'pagi');
    tahanRuangan('B202', '2026-06-20', 'siang');
    tahanRuangan('A101', '2026-07-05', 'pagi');

    $k = Livewire::actingAs(penataRuang())->test(PlotRuangan::class);
    $pagi = fn (array $d) => collect($d['minggu'])->flatten(1)->filter()->keyBy('tanggal');

    expect($pagi($k->instance()->dataKalender())['2026-06-20']['entri'])->toHaveCount(1)->and($pagi($k->instance()->dataKalender())['2026-06-20']['entri'][0]['sesi'])->toBe('pagi');

    $k->call('pilihRuangan', 'B202');
    expect($pagi($k->instance()->dataKalender())['2026-06-20']['entri'][0]['sesi'])->toBe('siang');

    $k->call('pilihRuangan', 'A101')->call('geser', 1)->assertSet('bulan', '2026-07');
    expect($pagi($k->instance()->dataKalender())['2026-07-05']['entri'][0]['sesi'])->toBe('pagi');
    $k->call('geser', -2)->assertSet('bulan', '2026-05');
});

it('menyembunyikan baris permohonan yang dilepas atau dibatalkan', function () {
    $p = tahanRuangan('A101', '2026-06-20', 'pagi');
    $p->ruangan()->update(['status' => PermohonanRuangan::DILEPAS]);

    $hari = collect(Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->instance()->dataKalender()['minggu'])->flatten(1)->filter()->keyBy('tanggal');

    expect($hari['2026-06-20']['entri'])->toBe([]);
});

it('menampilkan rincian permohonan hanya bagi yang berhak melihatnya', function () {
    $p = tahanRuangan('A101', '2026-06-20', 'pagi', 'Seminar Rahasia PLOT');

    Livewire::actingAs(penataRuang('admin-persuratan'))->test(PlotRuangan::class)->call('pilihPermohonan', $p->id)->assertSee('Seminar Rahasia PLOT')->assertSee('Buka permohonan');
    Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('pilihPermohonan', $p->id)->assertDontSee('Seminar Rahasia PLOT')->assertSee('Rincian tidak tersedia');
});

describe('pemakaian manual (mode lokal)', function () {
    it('mencatat pemakaian non-ormawa dan menampilkannya di kalender', function () {
        $k = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('pilihRuangan', 'A101')
            ->set('tanggalManual', '2026-06-10')->set('sesiManual', 'siang')->set('keteranganManual', 'Rapat dosen')->call('catatManual')->assertHasNoErrors();

        expect(PemakaianRuanganLokal::where('keterangan', 'Rapat dosen')->count())->toBe(1);
        $hari = collect($k->instance()->dataKalender()['minggu'])->flatten(1)->filter()->keyBy('tanggal');
        expect($hari['2026-06-10']['entri'][0])->toMatchArray(['sesi' => 'siang', 'jenis' => 'lain']);
    });

    it('menolak bentrok dengan pemakaian lain maupun sesi yang ditahan permohonan', function () {
        tahanRuangan('A101', '2026-06-20', 'pagi');
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-25', 'seharian');
        $k = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('pilihRuangan', 'A101');

        $k->set('tanggalManual', '2026-06-20')->set('sesiManual', 'seharian')->call('catatManual')->assertHasErrors('tanggalManual');
        $k->set('tanggalManual', '2026-06-25')->set('sesiManual', 'pagi')->call('catatManual')->assertHasErrors('tanggalManual');
        $k->set('tanggalManual', '2026-06-20')->set('sesiManual', 'siang')->call('catatManual')->assertHasNoErrors();

        expect(PemakaianRuanganLokal::count())->toBe(2);
    });

    it('memvalidasi isian dan menolak pengguna tanpa hak', function () {
        $k = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('pilihRuangan', 'A101');
        $k->set('tanggalManual', '20/06/2026')->call('catatManual')->assertHasErrors('tanggalManual');
        $k->set('tanggalManual', '2026-06-20')->set('sesiManual', 'malam')->call('catatManual')->assertHasErrors('sesiManual');
        expect(PemakaianRuanganLokal::count())->toBe(0);

        $biasa = User::factory()->create()->assignRole('operator-layanan');
        Livewire::actingAs($biasa)->test(PlotRuangan::class)->assertForbidden();
    });

    it('hanya menghapus pemakaian manual, bukan yang berasal dari permohonan', function () {
        $ruang = RuanganLokal::firstWhere('kode', 'A101');
        $manual = PemakaianRuanganLokal::create(['ruangan_lokal_id' => $ruang->id, 'tanggal' => '2026-06-10', 'sesi' => 'pagi', 'keterangan' => 'Manual']);
        $dariPermohonan = PemakaianRuanganLokal::create(['ruangan_lokal_id' => $ruang->id, 'tanggal' => '2026-06-11', 'sesi' => 'pagi', 'permohonan_id' => 'ref-1']);
        $k = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('pilihRuangan', 'A101');

        $k->call('hapusManual', $manual->id);
        expect(PemakaianRuanganLokal::whereKey($manual->id)->exists())->toBeFalse();

        Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('hapusManual', $dariPermohonan->id)->assertNotFound();
        expect(PemakaianRuanganLokal::whereKey($dariPermohonan->id)->exists())->toBeTrue();
    });
});

describe('mode Aset', function () {
    it('menampilkan jadwal dari API dan menyembunyikan pencatatan manual', function () {
        Pengaturan::set('layanan_ruangan', 'aset_api');
        config(['layanan.aset.url' => 'https://aset.test', 'layanan.aset.token' => 't']);
        Http::fake([
            'aset.test/api/v1/ruangan' => Http::response(['data' => [['kode' => 'R1', 'nama' => 'Aula Aset']]]),
            'aset.test/api/v1/ruangan/R1/jadwal*' => fn ($r) => Http::response(['data' => $r['dari'] === '2026-06-15' ? [['tanggal' => '2026-06-15', 'sesi' => 'pagi', 'keterangan' => 'Kuliah umum']] : []]),
        ]);

        $k = Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->assertSee('Aula Aset')->assertDontSee('Catat pemakaian manual');
        $hari = collect($k->instance()->dataKalender()['minggu'])->flatten(1)->filter()->keyBy('tanggal');

        expect($hari['2026-06-15']['entri'][0])->toMatchArray(['sesi' => 'pagi', 'jenis' => 'lain', 'label' => 'Kuliah umum']);
        $k->set('tanggalManual', '2026-06-20')->call('catatManual')->assertForbidden();
    });

    it('menampilkan pesan bila layanan tidak tersedia', function () {
        Pengaturan::set('layanan_ruangan', 'aset_api');
        config(['layanan.aset.url' => 'https://aset.test', 'layanan.aset.token' => 't']);
        Http::fake(['aset.test/*' => Http::response('', 500)]);

        Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->assertSee('Layanan ruangan sedang tidak tersedia');
    });
});

it('tidak memuat baris permohonan lain yang tidak berkaitan dengan ruangan itu', function () {
    $p = tahanRuangan('B202', '2026-06-20', 'pagi');

    $hari = collect(Livewire::actingAs(penataRuang())->test(PlotRuangan::class)->call('pilihRuangan', 'A101')->instance()->dataKalender()['minggu'])->flatten(1)->filter()->keyBy('tanggal');

    expect($hari['2026-06-20']['entri'])->toBe([])->and($p->exists)->toBeTrue()->and(Jabatan::count())->toBe(0);
});
