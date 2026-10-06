<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

it('memakai HasUuids di semua model aplikasi', function () {
    foreach (glob(app_path('Models/*.php')) as $berkas) {
        $kelas = 'App\\Models\\'.basename($berkas, '.php');

        expect(array_key_exists(HasUuids::class, class_uses_recursive($kelas)))
            ->toBeTrue("{$kelas} wajib memakai HasUuids");
    }
});

it('tidak memakai kunci auto-increment di migrasi', function () {
    $larangan = [
        '/->id\(/' => '->id()',
        '/foreignId\(/' => 'foreignId(',
        '/(?<!uuid|Uuid)[mM]orphs\(/' => 'morphs( tanpa awalan uuid',
        '/bigIncrements\(/' => 'bigIncrements(',
        '/(?<!big)increments\(/' => 'increments(',
    ];

    foreach (glob(database_path('migrations/*.php')) as $berkas) {
        $isi = file_get_contents($berkas);

        // tabel infrastruktur kerangka kerja (STANDAR-TEKNIS §4a butir 6)
        // tabel impor Filament: lihat docs/KEPUTUSAN.md
        if (preg_match('/_create_(cache|jobs|imports|failed_import_rows)_table\.php$/', $berkas)) {
            continue;
        }

        foreach ($larangan as $pola => $nama) {
            expect(preg_match($pola, $isi))->toBe(0, basename($berkas)." memuat {$nama}");
        }
    }
});
