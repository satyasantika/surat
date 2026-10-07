<?php

namespace App\Services\Pdf;

use App\Contracts\PembangkitPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Dompdf\Adapter\CPDF;

class PembangkitPdfDompdf implements PembangkitPdf
{
    public function render(string $html, ?CarbonInterface $tanggalDokumen = null): string
    {
        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        if ($tanggalDokumen !== null) {
            // Tanggal dan ID berkas dipaku: HTML yang sama selalu menghasilkan byte yang sama (hash dapat diverifikasi).
            $pdf->render();
            $dompdf = $pdf->getDomPDF();
            $tanggal = "D:{$tanggalDokumen->copy()->utc()->format('YmdHis')}Z";
            $dompdf->add_info('CreationDate', $tanggal);
            $dompdf->add_info('ModDate', $tanggal);
            /** @var CPDF $canvas */
            $canvas = $dompdf->getCanvas();
            $canvas->get_cpdf()->fileIdentifier = md5($html);
        }

        return $pdf->output();
    }
}
