<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

// Meniru reverse proxy yang sudah memotong awalan /surat sebelum meneruskan ke aplikasi.
const LANGSUNG = 'https://supportfkip.unsil.ac.id';

beforeEach(function () {
    config([
        'app.url' => 'https://supportfkip.unsil.ac.id/surat',
        'app.asset_url' => 'https://supportfkip.unsil.ac.id/surat',
        'session.path' => '/surat',
        'session.cookie' => 'surat_session',
    ]);

    app()->getProvider(AppServiceProvider::class)->boot();
});

afterEach(function () {
    URL::forceRootUrl(null);
    URL::forceScheme(null);
});

it('membangkitkan url dengan awalan subpath', function () {
    expect(url('/'))->toBe('https://supportfkip.unsil.ac.id/surat')
        ->and(asset('build/app.js'))->toStartWith('https://supportfkip.unsil.ac.id/surat/');
});

it('memasang cookie sesi surat_session ber-path /surat', function () {
    $cookie = collect($this->get(LANGSUNG.'/admin/login')->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'surat_session');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getPath())->toBe('/surat');
});

it('mengalihkan tamu ke login tetap di bawah subpath', function () {
    $this->get(LANGSUNG.'/admin')->assertRedirect('https://supportfkip.unsil.ac.id/surat/admin/login');
});
