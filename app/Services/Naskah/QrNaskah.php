<?php

namespace App\Services\Naskah;

use App\Models\Naskah;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/** QR verifikasi: berisi hanya URL publik ber-UUID (RS-05), dibangkitkan saat render. */
class QrNaskah
{
    public static function url(Naskah $naskah): string
    {
        return route('verifikasi', $naskah->getKey());
    }

    public function dataUri(Naskah $naskah): string
    {
        $hasil = (new Builder(writer: new PngWriter, data: self::url($naskah), size: 240, margin: 6))->build();

        return $hasil->getDataUri();
    }
}
