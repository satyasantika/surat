<?php

use App\Models\User;
use Database\Seeders\PanduanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function berkasPanduan(): array
{
    return collect(File::allFiles(public_path('panduan')))->filter(fn ($f) => $f->getExtension() === 'html')->all();
}

it('setiap peran sistem memiliki halaman panduan dan tercantum di indeks', function () {
    $indeks = file_get_contents(public_path('panduan/index.html'));

    foreach (array_keys(PeranDanIzinSeeder::PERAN) as $peran) {
        expect(public_path("panduan/{$peran}.html"))->toBeFile()->and($indeks)->toContain("href=\"{$peran}.html\"");
    }

    expect(public_path('panduan/publik.html'))->toBeFile()->and($indeks)->toContain('href="publik.html"');
});

it('setiap gambar panduan menunjuk berkas yang ada dan beralt deskriptif', function () {
    $jumlah = 0;

    foreach (berkasPanduan() as $f) {
        $html = file_get_contents($f->getPathname());
        preg_match_all('/<img\b[^>]*>/i', $html, $tag);

        foreach ($tag[0] as $img) {
            preg_match('/src="([^"]+)"/', $img, $src);
            preg_match('/alt="([^"]*)"/', $img, $alt);
            $jumlah++;

            expect(public_path('panduan/'.($src[1] ?? '')))->toBeFile("{$f->getFilename()} merujuk {$src[1]}")->and(strlen($alt[1] ?? ''))->toBeGreaterThan(10);
        }
    }

    expect($jumlah)->toBeGreaterThan(40);
});

it('semua href dan src relatif (aman untuk subpath), tanpa localhost dan tanpa CDN', function () {
    foreach (berkasPanduan() as $f) {
        $html = file_get_contents($f->getPathname());
        preg_match_all('/\b(?:href|src)="([^"]*)"/i', $html, $m);

        foreach ($m[1] as $url) {
            expect($url)->not->toStartWith('/')->not->toStartWith('http://localhost')->not->toStartWith('http://')->not->toStartWith('https://')->not->toStartWith('//');
        }

        expect($html)->not->toContain('<script')->not->toContain('cdn.')->and($html)->toContain('<style>');
    }
});

it('ukuran total public/panduan tidak melebihi 15 MB dan gambar PNG berlebar ≤ 1366', function () {
    $total = collect(File::allFiles(public_path('panduan')))->sum(fn ($f) => $f->getSize());

    expect($total)->toBeLessThanOrEqual(15 * 1024 * 1024);

    foreach (File::allFiles(public_path('panduan/img')) as $png) {
        [$lebar] = getimagesize($png->getPathname());
        expect($lebar)->toBeLessThanOrEqual(1366);
    }
});

it('landing page publik dan halaman masuk menautkan panduan', function () {
    $this->withoutVite();

    $this->get('/')->assertOk()->assertSee('panduan/index.html', false);
    $this->get('/masuk')->assertOk()->assertSee('panduan/index.html', false);
});

it('PanduanSeeder menolak lingkungan selain local dan butuh kata sandi dari lingkungan', function () {
    $this->seed(PeranDanIzinSeeder::class);

    foreach (['production', 'staging', 'testing'] as $env) {
        $this->app['env'] = $env;
        expect(fn () => (new PanduanSeeder)->run())->toThrow(RuntimeException::class, 'APP_ENV=local');
    }

    $this->app['env'] = 'local';
    config(['panduan.sandi' => null]);
    expect(fn () => (new PanduanSeeder)->run())->toThrow(RuntimeException::class, 'PANDUAN_PASSWORD');
    expect(User::count())->toBe(0);
});

it('MFA hanya dikecualikan untuk akun demo contoh.test di lokal dengan flag menyala', function () {
    $this->seed(PeranDanIzinSeeder::class);
    $demo = User::factory()->create(['email' => 'panduan.dekan@contoh.test'])->assignRole('dekan');
    $nyata = User::factory()->create(['email' => 'dekan@unsil.ac.id'])->assignRole('dekan');

    expect($demo->wajibMfa())->toBeTrue()->and($nyata->wajibMfa())->toBeTrue();

    config(['panduan.tanpa_mfa' => true]);
    expect($demo->wajibMfa())->toBeTrue(); // env testing, bukan local

    $this->app['env'] = 'local';
    expect($demo->wajibMfa())->toBeFalse()->and($nyata->wajibMfa())->toBeTrue();

    $this->app['env'] = 'production';
    expect($demo->wajibMfa())->toBeTrue();
});
