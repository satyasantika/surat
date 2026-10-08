<?php

use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menampilkan halaman 404 bermerek dengan tombol kembali dan beranda', function () {
    $r = $this->withoutVite()->get('/organisasi/tidak-ada-slug-ini')->assertNotFound();

    $r->assertSee('404')->assertSee('Halaman tidak ditemukan')
        ->assertSee('Kembali')->assertSee('Ke beranda')
        ->assertSee(route('beranda'), false);
});

it('menampilkan halaman 403 bermerek saat akses ditolak', function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');

    $tanpaIzin = User::factory()->create()->assignRole('pegawai');
    $tanpaIzin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    $r = $this->withoutVite()->actingAs($tanpaIzin)->get('/admin/pengguna')->assertForbidden();
    $r->assertSee('403')->assertSee('Akses ditolak')->assertSee('Kembali')->assertSee('Ke beranda');
});

it('halaman error memuat identitas FKIP', function () {
    $r = $this->withoutVite()->get('/kabar/tidak-ada-slug-ini')->assertNotFound();

    $r->assertSee(config('app.name'))->assertSee('FKIP Universitas Siliwangi');
});
