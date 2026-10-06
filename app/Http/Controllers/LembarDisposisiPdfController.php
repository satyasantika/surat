<?php

namespace App\Http\Controllers;

use App\Actions\Disposisi\HtmlLembarDisposisi;
use App\Contracts\PembangkitPdf;
use App\Models\SuratMasuk;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Lembar disposisi PDF: dirender saat diminta dan di-stream, tidak disimpan. */
class LembarDisposisiPdfController extends Controller
{
    public function __invoke(SuratMasuk $surat, HtmlLembarDisposisi $html, PembangkitPdf $pdf): Response
    {
        // Metadata saja sudah cukup untuk mencetak lembar; isi rahasia tetap disamarkan di HTML.
        Gate::authorize('lihatMetadata', $surat);

        $berkas = Str::slug('lembar-disposisi-'.$surat->nomor_agenda).'.pdf';

        return response($pdf->render($html->jalankan($surat, auth()->user())), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$berkas}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
