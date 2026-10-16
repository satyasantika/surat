<?php

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): contoh env tidak boleh kembali ke Redis/Gotenberg,
 * dan driver PDF bawaan adalah dompdf.
 */
dataset('contoh env', ['.env.example', '.env.production.example']);

it('memakai driver database dan dompdf pada contoh env', function (string $berkas) {
    $isi = file_get_contents(base_path($berkas));

    expect($isi)
        ->toContain("\nSESSION_DRIVER=database\n")
        ->toContain("\nQUEUE_CONNECTION=database\n")
        ->toContain("\nCACHE_STORE=database\n")
        ->toContain("\nPDF_DRIVER=dompdf\n")
        ->toContain("\nAPP_KODE=surat\n")
        ->not->toMatch('/^REDIS_/m')
        ->not->toMatch('/^GOTENBERG_URL=/m')
        ->not->toContain('_DRIVER=redis')
        ->not->toContain('QUEUE_CONNECTION=redis')
        ->not->toContain('CACHE_STORE=redis');
})->with('contoh env');

it('menetapkan dompdf sebagai driver pdf bawaan di config', function () {
    expect(file_get_contents(config_path('pdf.php')))->toContain("env('PDF_DRIVER', 'dompdf')");
});
