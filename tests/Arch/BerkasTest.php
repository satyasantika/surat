<?php

use Illuminate\Support\Facades\File;

it('tidak menyediakan unggahan berkas di aplikasi (kebijakan tautan, STANDAR-TEKNIS §1a)', function () {
    $larangan = [
        '/\bFileUpload\b/' => 'komponen FileUpload',
        '/SpatieMediaLibrary/' => 'media library',
        '/\bUploadedFile\b/' => 'UploadedFile',
        '/->(hasFile|file|allFiles)\(/' => 'akses berkas unggahan request',
        '/Storage::(put|putFile|putFileAs)\(/' => 'penyimpanan berkas',
        '/->store(As|Publicly)?\(/' => 'penyimpanan berkas upload',
        '/type\s*=\s*["\']file["\']/i' => 'input type=file',
        '/WithFileUploads/' => 'Livewire WithFileUploads',
    ];

    $berkas = collect([app_path(), resource_path('views')])
        ->flatMap(fn (string $dir) => File::allFiles($dir))
        ->filter(fn ($f) => in_array($f->getExtension(), ['php'], true));

    expect($berkas)->not->toBeEmpty();

    foreach ($berkas as $f) {
        $isi = file_get_contents($f->getPathname());

        foreach ($larangan as $regex => $nama) {
            expect(preg_match($regex, $isi))->toBe(0, "{$f->getPathname()} memuat {$nama}");
        }
    }
});

it('mendeteksi pelanggaran pada contoh buruk', function () {
    expect(preg_match('/\bFileUpload\b/', 'FileUpload::make("x")'))->toBe(1)
        ->and(preg_match('/type\s*=\s*["\']file["\']/i', '<input type="file">'))->toBe(1);
});
