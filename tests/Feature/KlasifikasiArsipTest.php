<?php

use App\Filament\Imports\KlasifikasiArsipImporter;
use App\Filament\Resources\KlasifikasiArsips\Pages\ManageKlasifikasiArsips;
use App\Models\KlasifikasiArsip;
use App\Models\User;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

function pengelolaKlasifikasi(): User
{
    $u = User::factory()->create()->assignRole('operator-layanan');
    $u->givePermissionTo('master.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function pemetaan(): array
{
    return array_combine(
        ['kode', 'nama', 'induk_kode', 'retensi_aktif', 'retensi_inaktif', 'keterangan_akhir'],
        ['kode', 'nama', 'induk_kode', 'retensi_aktif', 'retensi_inaktif', 'keterangan_akhir'],
    );
}

it('menyemai contoh klasifikasi dengan hierarki', function () {
    $this->seed(KlasifikasiArsipSeeder::class);
    $this->seed(KlasifikasiArsipSeeder::class);

    $daun = KlasifikasiArsip::firstWhere('kode', 'KM.03.02');

    expect(KlasifikasiArsip::count())->toBe(6)
        ->and($daun->induk->kode)->toBe('KM.03')
        ->and($daun->induk->induk->kode)->toBe('KM')
        ->and(KlasifikasiArsip::firstWhere('kode', 'KM')->anak)->toHaveCount(1);
});

it('mencegah siklus pada hierarki', function () {
    $a = KlasifikasiArsip::create(['kode' => 'A', 'nama' => 'A']);
    $b = KlasifikasiArsip::create(['kode' => 'B', 'nama' => 'B', 'induk_id' => $a->id]);

    expect(fn () => $a->update(['induk_id' => $b->id]))->toThrow(ValidationException::class)
        ->and(fn () => $a->update(['induk_id' => $a->id]))->toThrow(ValidationException::class);
});

it('meng-cache pilihan klasifikasi dan membatalkannya saat berubah', function () {
    $a = KlasifikasiArsip::create(['kode' => 'A', 'nama' => 'Satu']);

    expect(KlasifikasiArsip::untukPilihan())->toBe([$a->id => 'A — Satu'])
        ->and(Cache::has(KlasifikasiArsip::CACHE_KUNCI))->toBeTrue();

    $b = KlasifikasiArsip::create(['kode' => 'B', 'nama' => 'Dua']);
    expect(Cache::has(KlasifikasiArsip::CACHE_KUNCI))->toBeFalse()
        ->and(KlasifikasiArsip::untukPilihan())->toHaveCount(2);

    $b->update(['aktif' => false]);
    expect(KlasifikasiArsip::untukPilihan())->toHaveCount(1);
});

it('mengimpor baris dengan induk dan retensi', function () {
    KlasifikasiArsipImporter::test(pemetaan())
        ->import(['kode' => 'KM', 'nama' => 'Kemahasiswaan', 'induk_kode' => '', 'retensi_aktif' => '', 'retensi_inaktif' => '', 'keterangan_akhir' => ''])
        ->assertImported()
        ->import(['kode' => 'KM.03', 'nama' => 'Pembinaan', 'induk_kode' => 'KM', 'retensi_aktif' => '2', 'retensi_inaktif' => '3', 'keterangan_akhir' => 'musnah'])
        ->assertImported();

    $k = KlasifikasiArsip::firstWhere('kode', 'KM.03');
    expect($k->induk->kode)->toBe('KM')
        ->and($k->retensi_aktif_tahun)->toBe(2)
        ->and($k->retensi_inaktif_tahun)->toBe(3)
        ->and($k->keterangan_akhir)->toBe('musnah');
});

it('memperbarui klasifikasi yang sudah ada berdasarkan kode', function () {
    KlasifikasiArsip::create(['kode' => 'UM', 'nama' => 'Lama']);

    KlasifikasiArsipImporter::test(pemetaan())
        ->import(['kode' => 'UM', 'nama' => 'Umum', 'induk_kode' => '', 'retensi_aktif' => '', 'retensi_inaktif' => '', 'keterangan_akhir' => ''])
        ->assertImported();

    expect(KlasifikasiArsip::where('kode', 'UM')->count())->toBe(1)
        ->and(KlasifikasiArsip::firstWhere('kode', 'UM')->nama)->toBe('Umum');
});

it('menggagalkan baris yang induknya belum ada', function () {
    KlasifikasiArsipImporter::test(pemetaan())
        ->import(['kode' => 'X1', 'nama' => 'X', 'induk_kode' => 'TAKADA', 'retensi_aktif' => '', 'retensi_inaktif' => '', 'keterangan_akhir' => ''])
        ->assertHasRowFailure('Induk TAKADA belum ada; cantumkan induk sebelum anaknya.');
});

it('menolak baris yang tidak lolos validasi', function (array $baris) {
    $hasil = KlasifikasiArsipImporter::test(pemetaan())
        ->import($baris + ['kode' => 'X1', 'nama' => 'X', 'induk_kode' => '', 'retensi_aktif' => '', 'retensi_inaktif' => '', 'keterangan_akhir' => '']);

    expect($hasil->errors()->isNotEmpty())->toBeTrue();
    expect(KlasifikasiArsip::count())->toBe(0);
})->with([
    'keterangan akhir salah' => [['keterangan_akhir' => 'hapus']],
    'retensi bukan angka' => [['retensi_aktif' => 'abc']],
    'kode kosong' => [['kode' => '']],
    'kode terlalu panjang' => [['kode' => str_repeat('K', 21)]],
]);

it('mengimpor berkas CSV lewat aksi impor di halaman', function () {
    $this->actingAs(pengelolaKlasifikasi());
    $csv = "kode,nama,induk_kode,retensi_aktif,retensi_inaktif,keterangan_akhir\nPK,Kepegawaian,,,,\nPK.01,Pengadaan,PK,5,10,permanen\n";
    $berkas = UploadedFile::fake()->createWithContent('klasifikasi.csv', $csv);

    Livewire::test(ManageKlasifikasiArsips::class)
        ->callAction(ImportAction::class, data: ['file' => $berkas, 'columnMap' => pemetaan()])
        ->assertHasNoActionErrors();

    expect(KlasifikasiArsip::pluck('kode')->sort()->values()->all())->toBe(['PK', 'PK.01'])
        ->and(KlasifikasiArsip::firstWhere('kode', 'PK.01')->induk->kode)->toBe('PK');
});

it('membatasi halaman klasifikasi ke master.kelola', function () {
    $this->actingAs(pengelolaKlasifikasi())->get('/admin/klasifikasi-arsip')->assertOk();

    $lain = User::factory()->create()->assignRole('admin-persuratan');
    $lain->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($lain)->get('/admin/klasifikasi-arsip')->assertForbidden();
});
