<?php

it('hanya memakai antrean default dan berat', function () {
    $diizinkan = ['default', 'berat'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() !== 'php') {
            continue;
        }

        preg_match_all('/onQueue\(\s*[\'"]([^\'"]+)[\'"]/', file_get_contents($berkas->getPathname()), $cocok);

        foreach ($cocok[1] as $nama) {
            expect($nama)->toBeIn($diizinkan, str_replace(app_path().'/', '', $berkas->getPathname())." memakai antrean '{$nama}'");
        }
    }
});

it('tidak memakai Redis di kode aplikasi', function () {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() !== 'php') {
            continue;
        }

        expect(file_get_contents($berkas->getPathname()))
            ->not->toContain('Redis::')
            ->not->toContain('Illuminate\\Support\\Facades\\Redis');
    }
});
