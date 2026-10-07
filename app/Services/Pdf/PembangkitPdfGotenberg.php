<?php

namespace App\Services\Pdf;

use App\Contracts\PembangkitPdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PembangkitPdfGotenberg implements PembangkitPdf
{
    public function __construct(private readonly string $url) {}

    public function render(string $html, ?CarbonInterface $tanggalDokumen = null): string
    {
        $isian = ['paperWidth' => '8.27', 'paperHeight' => '11.7', 'printBackground' => 'true'];

        if ($tanggalDokumen !== null) {
            // Determinisme byte tidak dijamin Chromium; naskah resmi memakai driver deterministik (config pdf.driver_naskah).
            $isian['metadata'] = json_encode(['CreationDate' => $tanggalDokumen->utc()->format('Y-m-d\TH:i:s\Z'), 'ModDate' => $tanggalDokumen->utc()->format('Y-m-d\TH:i:s\Z')]);
        }

        $respons = Http::timeout(60)
            ->attach('files', $html, 'index.html')
            ->post(rtrim($this->url, '/').'/forms/chromium/convert/html', $isian);

        if ($respons->failed()) {
            throw new RuntimeException('Gotenberg gagal merender PDF (HTTP '.$respons->status().').');
        }

        return $respons->body();
    }
}
