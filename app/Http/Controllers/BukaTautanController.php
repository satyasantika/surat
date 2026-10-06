<?php

namespace App\Http\Controllers;

use App\Contracts\PenyimpananBerkas;
use App\Models\TautanBerkas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Pengalihan ke tautan eksternal setelah otorisasi pemilik (URL tidak dibocorkan ke yang tak berhak). */
class BukaTautanController extends Controller
{
    public function __invoke(TautanBerkas $tautan, PenyimpananBerkas $penyimpanan): RedirectResponse
    {
        Gate::authorize('view', $tautan);

        $url = $penyimpanan->urlAkses($tautan);
        abort_if($url === null, 404);

        return redirect()->away($url);
    }
}
