<?php

namespace App\Http\Controllers;

use App\Actions\Naskah\RenderHtmlNaskah;
use App\Models\Naskah;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Pratinjau HTML naskah (draf hidup atau snapshot). Semua nilai ter-escape oleh templat. */
class PratinjauNaskahController extends Controller
{
    public function __invoke(Naskah $naskah, RenderHtmlNaskah $render): Response
    {
        Gate::authorize('view', $naskah);

        return response($render->jalankan($naskah->konteksRender()), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
        ]);
    }
}
