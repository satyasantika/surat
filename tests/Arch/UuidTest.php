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

it('tidak memakai char/binary eksplisit untuk kolom id di migrasi', function () {
    // kolom id/FK/morph wajib lewat helper Laravel (uuid, foreignUuid, uuidMorphs) agar tipenya sama
    $larangan = [
        '/->char\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*36/' => "char('..._id', 36)",
        '/->binary\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*16/' => "binary('..._id', 16)",
    ];

    // id_baru: kolom jejak impor OrmawaHub, bukan FK; migrasi sudah dirilis (docs/KEPUTUSAN.md K-08)
    $pengecualian = ['2026_10_09_120000_create_impor_ormawahub_log_table.php'];

    foreach (glob(database_path('migrations/*.php')) as $berkas) {
        if (in_array(basename($berkas), $pengecualian, true)) {
            continue;
        }

        $isi = file_get_contents($berkas);

        foreach ($larangan as $pola => $nama) {
            expect(preg_match($pola, $isi))->toBe(0, basename($berkas)." memuat {$nama}");
        }
    }
});
