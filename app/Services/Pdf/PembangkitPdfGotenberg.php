<?php

namespace App\Services\Pdf;

use App\Contracts\PembangkitPdf;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PembangkitPdfGotenberg implements PembangkitPdf
{
    public function __construct(private readonly string $url) {}

    public function render(string $html): string
    {
        $respons = Http::timeout(60)
            ->attach('files', $html, 'index.html')
            ->post(rtrim($this->url, '/').'/forms/chromium/convert/html', [
                'paperWidth' => '8.27',
                'paperHeight' => '11.7',
                'printBackground' => 'true',
            ]);

        if ($respons->failed()) {
            throw new RuntimeException('Gotenberg gagal merender PDF (HTTP '.$respons->status().').');
        }

        return $respons->body();
    }
}
