<?php

use Illuminate\Support\Facades\File;

it('hanya komponen isi-aman yang mengeluarkan HTML mentah ({!! !!}) dan ia menyanitasi ulang', function () {
    $pelanggar = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))
        ->filter(fn ($f) => str_contains(file_get_contents($f->getPathname()), '{!!') && $f->getRelativePathname() !== 'components/isi-aman.blade.php')
        ->map(fn ($f) => $f->getRelativePathname())->values()->all();

    expect($pelanggar)->toBe([])
        ->and(file_get_contents(resource_path('views/components/isi-aman.blade.php')))->toContain('SanitasiHtml::bersihkan');
});

it('tidak membangun HtmlString atau Blade::render dari masukan di kode aplikasi', function () {
    // Pengecualian tertulis: tidak ada. Daftar ini harus kosong; tambahkan hanya dengan tinjauan keamanan.
    $pola = '/new HtmlString\(|Blade::render\(|->html\(\)|Str::of\([^)]*\)->markdown|\bunserialize\(|\beval\(/';
    $kena = collect(File::allFiles(app_path()))->filter(fn ($f) => $f->getExtension() === 'php' && preg_match($pola, file_get_contents($f->getPathname())) === 1)
        ->map(fn ($f) => $f->getRelativePathname())->values()->all();

    expect($kena)->toBe([]);
});

it('mendeteksi pelanggaran pada contoh buruk', function () {
    expect(preg_match('/new HtmlString\(|Blade::render\(/', 'return new HtmlString($x);'))->toBe(1);
});
