<?php

use Illuminate\Support\Facades\File;

it('tidak memakai URL absolut berawalan slash di view dan js', function () {
    $pola = [
        '/\b(?:href|src|action)\s*=\s*["\']\/(?!\/)/' => 'href/src/action="/…"',
        '/\bfetch\(\s*[\'"`]\/(?!\/)/' => "fetch('/…')",
        '/\baxios(?:\.\w+)?\(\s*[\'"`]\/(?!\/)/' => "axios('/…')",
    ];

    $berkas = collect([resource_path('views'), resource_path('js')])
        ->filter(fn (string $dir) => is_dir($dir))
        ->flatMap(fn (string $dir) => File::allFiles($dir))
        ->filter(fn ($f) => preg_match('/\.(php|js|ts|vue|html)$/', $f->getFilename()));

    expect($berkas)->not->toBeEmpty();

    foreach ($berkas as $f) {
        $isi = file_get_contents($f->getPathname());

        foreach ($pola as $regex => $nama) {
            expect(preg_match($regex, $isi))->toBe(0, "{$f->getPathname()} memuat {$nama}");
        }
    }
});

it('mendeteksi pelanggaran pada contoh buruk', function () {
    expect(preg_match('/\b(?:href|src|action)\s*=\s*["\']\/(?!\/)/', '<a href="/login">'))->toBe(1)
        ->and(preg_match('/\b(?:href|src|action)\s*=\s*["\']\/(?!\/)/', '<a href="{{ route(\'login\') }}">'))->toBe(0);
});
