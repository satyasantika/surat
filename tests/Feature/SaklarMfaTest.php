<?php

use App\Filament\Pages\PengaturanSistem;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
    Cache::forget(Pengaturan::CACHE_KUNCI);
});

it('MFA tidak wajib bawaan untuk semua peran', function () {
    foreach (config('unsil.peran_wajib_mfa') as $peran) {
        expect(User::factory()->create()->assignRole($peran)->wajibMfa())->toBeFalse("peran {$peran}");
    }
});

it('menyalakan saklar mewajibkan MFA hanya untuk peran terdaftar', function () {
    Pengaturan::set('mfa_wajib', true);
    Pengaturan::lupakan();

    expect(User::factory()->create()->assignRole('dekan')->wajibMfa())->toBeTrue()
        ->and(User::factory()->create()->assignRole('mahasiswa')->wajibMfa())->toBeFalse();
});

it('hanya super-admin yang dapat mengubah saklar', function () {
    $admin = User::factory()->create()->assignRole('admin-persuratan');
    $admin->givePermissionTo('pengaturan.kelola');
    $this->actingAs($admin);

    Livewire::test(PengaturanSistem::class)->set('data.mfa_wajib', true)->call('simpan');
    Pengaturan::lupakan();
    expect(Pengaturan::get('mfa_wajib'))->toBeFalse();

    $this->actingAs(User::factory()->create()->assignRole('super-admin'));
    Livewire::test(PengaturanSistem::class)->set('data.mfa_wajib', true)->call('simpan');
    Pengaturan::lupakan();
    expect(Pengaturan::get('mfa_wajib'))->toBeTrue();
});

it('akses panel tanpa MFA terpasang tidak dialihkan saat saklar mati', function () {
    $dekan = User::factory()->create()->assignRole('dekan');

    $respons = $this->actingAs($dekan)->get('/admin');

    expect((string) $respons->headers->get('Location'))->not->toContain('multi-factor-authentication/set-up');
});

it('membaca pengaturan saat tabel belum ada tidak melempar galat', function () {
    // DDL meng-commit transaksi tes; tabel dipulihkan agar tes lain pada basis data bersama tidak terganggu.
    Schema::rename('pengaturan', 'pengaturan_sementara');

    try {
        Cache::forget(Pengaturan::CACHE_KUNCI);

        expect(Pengaturan::get('mfa_wajib'))->toBeFalse();
    } finally {
        Schema::rename('pengaturan_sementara', 'pengaturan');
        Cache::forget(Pengaturan::CACHE_KUNCI);
    }
});
