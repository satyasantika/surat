<?php

use App\Filament\Pages\PengaturanSistem;
use App\Models\Aktivitas;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Pengaturan as Setelan;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class]);
    Filament::setCurrentPanel('admin');
    Cache::forget(Setelan::CACHE_KUNCI);
});

function superAdminPengaturan(): User
{
    $u = User::factory()->create()->assignRole('super-admin');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

it('menyemai seluruh kunci pengaturan awal', function () {
    expect(Pengaturan::pluck('kunci')->sort()->values()->all())->toBe(collect(array_keys(Setelan::BAWAAN))->sort()->values()->all())
        ->and(Setelan::get('min_hari_sebelum_kegiatan'))->toBe(7)
        ->and(Setelan::get('batas_hari_lpj'))->toBe(14)
        ->and(Setelan::get('blokir_lpj_terlambat'))->toBeTrue()
        ->and(Setelan::get('persetujuan_pembina_aktif'))->toBeFalse()
        ->and(Setelan::get('layanan_ruangan'))->toBe('lokal')
        ->and(Setelan::get('sesi'))->toHaveCount(3);
});

it('tidak menimpa nilai yang sudah diubah saat disemai ulang', function () {
    Setelan::set('batas_hari_lpj', 30);
    $this->seed(PengaturanSeeder::class);

    expect(Setelan::get('batas_hari_lpj'))->toBe(30);
});

it('meng-cache pengaturan dan membatalkan cache saat berubah', function () {
    Setelan::get('batas_hari_lpj');
    expect(Cache::has(Setelan::CACHE_KUNCI))->toBeTrue();

    DB::enableQueryLog();
    Setelan::get('batas_hari_lpj');
    Setelan::get('toleransi_lpj_hari');
    expect(DB::getQueryLog())->toBeEmpty();

    Setelan::set('batas_hari_lpj', 21);
    expect(Cache::has(Setelan::CACHE_KUNCI))->toBeFalse()
        ->and(Setelan::get('batas_hari_lpj'))->toBe(21);
});

it('memakai bawaan untuk kunci yang belum ada di basis data', function () {
    Pengaturan::where('kunci', 'wa_aktif')->delete();

    expect(Setelan::get('wa_aktif'))->toBeFalse()
        ->and(Setelan::get('kunci_tak_dikenal', 'x'))->toBe('x');
});

it('mencatat perubahan pengaturan di log aktivitas', function () {
    $this->actingAs(superAdminPengaturan());
    Setelan::set('toleransi_lpj_hari', 3);

    expect(Aktivitas::where('log_name', 'pengaturan')->where('event', 'updated')->exists())->toBeTrue();
});

it('hanya super-admin yang membuka halaman pengaturan', function () {
    $this->actingAs(superAdminPengaturan())->get('/admin/pengaturan')->assertOk();

    $admin = User::factory()->create()->assignRole('admin-persuratan');
    $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($admin)->get('/admin/pengaturan')->assertForbidden();
});

it('menyimpan perubahan dari halaman pengaturan', function () {
    $this->actingAs(superAdminPengaturan());

    Livewire::test(PengaturanSistem::class)
        ->assertFormSet(['min_hari_sebelum_kegiatan' => 7, 'layanan_ruangan' => 'lokal'])
        ->assertFormSet(function (array $state) {
            expect(array_column($state['kop_surat'], 'baris'))->toBe(Setelan::get('kop_surat'));

            return true;
        })
        ->fillForm([
            'min_hari_sebelum_kegiatan' => 10, 'batas_hari_lpj' => 21, 'toleransi_lpj_hari' => 2,
            'blokir_lpj_terlambat' => false, 'persetujuan_pembina_aktif' => true, 'wa_aktif' => true,
            'layanan_ruangan' => 'aset_api',
            'kop_surat' => [['baris' => 'BARIS SATU'], ['baris' => 'BARIS DUA']],
        ])
        ->call('simpan')
        ->assertHasNoFormErrors();

    expect(Setelan::get('min_hari_sebelum_kegiatan'))->toBe(10)
        ->and(Setelan::get('blokir_lpj_terlambat'))->toBeFalse()
        ->and(Setelan::get('persetujuan_pembina_aktif'))->toBeTrue()
        ->and(Setelan::get('layanan_ruangan'))->toBe('aset_api')
        ->and(Setelan::get('kop_surat'))->toBe(['BARIS SATU', 'BARIS DUA']);
});

it('menolak nilai tidak sah di halaman pengaturan', function (array $ubah, array $galat) {
    $this->actingAs(superAdminPengaturan());

    Livewire::test(PengaturanSistem::class)
        ->fillForm($ubah)
        ->call('simpan')
        ->assertHasFormErrors($galat);

    expect(Setelan::get('min_hari_sebelum_kegiatan'))->toBe(7);
})->with([
    'hari negatif' => [['min_hari_sebelum_kegiatan' => -1], ['min_hari_sebelum_kegiatan']],
    'batas lpj nol' => [['batas_hari_lpj' => 0], ['batas_hari_lpj']],
    'sesi jam salah' => [['sesi' => [['kode' => 'x', 'mulai' => '25:00', 'selesai' => '26:00']]], ['sesi.0.mulai']],
    'sesi selesai sebelum mulai' => [['sesi' => [['kode' => 'x', 'mulai' => '12:00', 'selesai' => '08:00']]], ['sesi.0.selesai']],
]);

it('menolak penyimpanan oleh pengguna tanpa izin', function () {
    $this->actingAs(superAdminPengaturan());
    $komponen = Livewire::test(PengaturanSistem::class);

    $biasa = User::factory()->create()->assignRole('pegawai');
    $this->actingAs($biasa);

    $komponen->call('simpan')->assertForbidden();
});
