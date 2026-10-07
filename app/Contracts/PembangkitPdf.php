<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface PembangkitPdf
{
    /**
     * Render HTML menjadi PDF dan kembalikan byte-nya.
     */
    /**
     * @param  CarbonInterface|null  $tanggalDokumen  bila diberikan, metadata tanggal dipaku agar keluaran deterministik
     */
    public function render(string $html, ?CarbonInterface $tanggalDokumen = null): string;
}
