<?php

namespace App\Services\Pdf;

use App\Contracts\PembangkitPdf;
use Barryvdh\DomPDF\Facade\Pdf;

class PembangkitPdfDompdf implements PembangkitPdf
{
    public function render(string $html): string
    {
        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}
