<?php

use App\Contracts\PembangkitPdf;
use App\Services\Pdf\PembangkitPdfDompdf;
use App\Services\Pdf\PembangkitPdfGotenberg;
use Illuminate\Support\Facades\Http;

it('merender pdf valid dengan driver dompdf', function () {
    config(['pdf.driver' => 'dompdf']);

    $pdf = app(PembangkitPdf::class);

    expect($pdf)->toBeInstanceOf(PembangkitPdfDompdf::class)
        ->and($pdf->render('<h1>Uji</h1>'))->toStartWith('%PDF');
});

it('memanggil gotenberg lewat http untuk driver gotenberg', function () {
    config(['pdf.driver' => 'gotenberg', 'pdf.gotenberg_url' => 'http://gotenberg.test:3000']);
    Http::fake(['gotenberg.test:3000/*' => Http::response('%PDF-1.7 palsu', 200)]);

    $pdf = app(PembangkitPdf::class);

    expect($pdf)->toBeInstanceOf(PembangkitPdfGotenberg::class)
        ->and($pdf->render('<p>Uji</p>'))->toStartWith('%PDF');

    Http::assertSent(fn ($r) => $r->url() === 'http://gotenberg.test:3000/forms/chromium/convert/html');
});

it('melempar galat bila gotenberg gagal', function () {
    config(['pdf.driver' => 'gotenberg', 'pdf.gotenberg_url' => 'http://gotenberg.test:3000']);
    Http::fake(['*' => Http::response('', 500)]);

    app(PembangkitPdf::class)->render('<p>x</p>');
})->throws(RuntimeException::class);
