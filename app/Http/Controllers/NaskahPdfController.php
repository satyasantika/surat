<?php

namespace App\Http\Controllers;

use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Services\Naskah\RenderNaskah;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** PDF naskah resmi: dirender ulang dari snapshot dan di-stream (tidak disimpan). */
class NaskahPdfController extends Controller
{
    public function __invoke(Naskah $naskah, RenderNaskah $render): Response
    {
        Gate::authorize('view', $naskah);
        abort_unless($naskah->snapshot !== null && $naskah->status !== StatusNaskah::Dibatalkan, 404);

        $pdf = $render->pdf($naskah);

        if ($naskah->hash_pdf !== null && ! hash_equals($naskah->hash_pdf, RenderNaskah::hash($pdf))) {
            Log::warning('Hash render ulang naskah tidak cocok dengan hash_pdf tercatat.', ['naskah' => $naskah->getKey()]);
        }

        $berkas = Str::slug('naskah-'.$naskah->nomor).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$berkas}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
