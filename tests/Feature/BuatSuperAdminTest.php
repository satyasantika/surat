<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('membuat super-admin dengan peran dan surel terverifikasi', function () {
    $this->artisan('surat:buat-superadmin', ['--name' => 'Admin Utama', '--email' => 'admin@unsil.ac.id'])
        ->expectsQuestion('Kata sandi (min. 12 karakter, huruf dan angka)', 'RahasiaKuat12345')
        ->assertSuccessful();

    $u = User::firstWhere('email', 'admin@unsil.ac.id');

    expect($u->hasRole('super-admin'))->toBeTrue()
        ->and($u->hasVerifiedEmail())->toBeTrue()
        ->and($u->aktif)->toBeTrue()
        ->and(Hash::check('RahasiaKuat12345', $u->password))->toBeTrue()
        ->and($u->wajibMfa())->toBeTrue();
});

it('menolak surel luar domain, sandi lemah, dan surel ganda', function (string $surel, string $sandi) {
    User::factory()->create(['email' => 'ada@unsil.ac.id']);

    $this->artisan('surat:buat-superadmin', ['--name' => 'X', '--email' => $surel])
        ->expectsQuestion('Kata sandi (min. 12 karakter, huruf dan angka)', $sandi)
        ->assertFailed();

    expect(User::where('email', $surel)->exists())->toBe($surel === 'ada@unsil.ac.id');
})->with([
    ['x@gmail.com', 'RahasiaKuat12345'],
    ['baru@unsil.ac.id', 'pendek1'],
    ['baru@unsil.ac.id', 'hurufsajahurufsaja'],
    ['ada@unsil.ac.id', 'RahasiaKuat12345'],
]);
