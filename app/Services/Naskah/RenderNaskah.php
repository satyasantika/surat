<?php

namespace App\Services\Naskah;

use App\Actions\Naskah\RenderHtmlNaskah;
use App\Contracts\PembangkitPdf;
use App\Models\Naskah;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Render naskah resmi: selalu dari snapshot beku (bukan data hidup), ditambah QR verifikasi. Keluaran PDF
 * memakai driver deterministik (config pdf.driver_naskah) sehingga hash dapat dicocokkan ulang.
 */
class RenderNaskah
{
    public function __construct(private readonly RenderHtmlNaskah $html, private readonly QrNaskah $qr) {}

    public function html(Naskah $naskah): string
    {
        if ($naskah->snapshot === null) {
            throw new LogicException('Naskah belum ditandatangani: tidak ada snapshot untuk dirender.');
        }

        $konteks = $naskah->snapshot;
        $konteks['qr'] = $this->qr->dataUri($naskah);

        return $this->html->jalankan($konteks);
    }

    public function pdf(Naskah $naskah): string
    {
        /** @var PembangkitPdf $pembangkit */
        $pembangkit = app('pdf.naskah');
        $tanggal = Carbon::parse($naskah->snapshot['ditandatangani_pada'] ?? $naskah->ditandatangani_pada);

        return $pembangkit->render($this->html($naskah), $tanggal);
    }

    public static function hash(string $pdf): string
    {
        return hash('sha256', $pdf);
    }
}
