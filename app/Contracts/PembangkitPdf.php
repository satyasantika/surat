<?php

namespace App\Contracts;

interface PembangkitPdf
{
    /**
     * Render HTML menjadi PDF dan kembalikan byte-nya.
     */
    public function render(string $html): string;
}
