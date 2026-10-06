<?php

use Illuminate\Support\Facades\File;

it('tidak mengambil pelaku audit dari input pengguna', function () {
    $berkas = collect(File::allFiles(app_path()))->filter(fn ($f) => $f->getExtension() === 'php');

    expect($berkas)->not->toBeEmpty();

    foreach ($berkas as $f) {
        foreach (file($f->getPathname()) as $no => $baris) {
            if (str_contains($baris, 'causedBy(') && preg_match('/request\(|\$request|->input\(|\$data\[/', $baris)) {
                $this->fail("{$f->getPathname()}:".($no + 1).' memakai causedBy() dengan nilai dari input');
            }
        }
    }

    expect(true)->toBeTrue();
});
