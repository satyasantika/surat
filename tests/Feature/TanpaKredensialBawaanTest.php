<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('seeder produksi tidak membuat akun apa pun', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0);
});

it('tidak ada kata sandi bawaan atau fallback di kode aplikasi, seeder, dan konfigurasi', function () {
    $pola = '/(password|kata_sandi|sandi)[\'"]?\s*(=>|=)\s*[\'"](admin|admin123|password|12345678|123456|rahasia|secret|qwerty)[\'"]/i';
    $berkas = collect([app_path(), database_path('seeders'), config_path()])->flatMap(fn ($d) => File::allFiles($d))->filter(fn ($f) => $f->getExtension() === 'php');

    $kena = $berkas->filter(fn ($f) => preg_match($pola, file_get_contents($f->getPathname())) === 1)->map(fn ($f) => $f->getRelativePathname())->values()->all();

    expect($kena)->toBe([]);
});

it('kata sandi lama tak dikenal: login dengan kredensial umum selalu gagal', function () {
    $this->seed(PeranDanIzinSeeder::class);

    foreach ([['admin', 'admin123'], ['admin@unsil.ac.id', 'admin123'], ['admin@unsil.ac.id', 'password'], ['dekan', 'dekan123'], ['ormawa', 'ormawa123'], ['super-admin@unsil.ac.id', '12345678']] as [$email, $sandi]) {
        $this->post('/masuk', ['email' => $email, 'password' => $sandi])->assertSessionHasErrors();
        $this->assertGuest();
        RateLimiter::clear(strtolower($email).'|127.0.0.1');
    }
});

it('akun super-admin hanya lewat perintah interaktif dan kata sandi dari masukan, bukan argumen', function () {
    $sumber = file_get_contents(app_path('Console/Commands/BuatSuperAdmin.php'));

    expect($sumber)->not->toContain('{--password')->and($sumber)->toContain('password(')->and(Hash::needsRehash(Hash::make('x')))->toBeFalse();
});

it('pabrik pengguna tidak dipakai di seeder produksi', function () {
    foreach (File::allFiles(database_path('seeders')) as $f) {
        expect(file_get_contents($f->getPathname()))->not->toContain('User::factory');
    }
});
