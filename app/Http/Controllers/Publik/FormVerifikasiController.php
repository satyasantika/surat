<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Pintu masuk verifikasi naskah: tempel kode atau tautan QR; hasil tetap ditangani VerifikasiController. */
class FormVerifikasiController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $kode = trim((string) $request->query('kode', ''));

        if ($kode === '') {
            return view('publik.verifikasi-form', ['galat' => null]);
        }

        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', Str::limit($kode, 300, ''), $m) === 1) {
            return redirect()->route('verifikasi', strtolower($m[0]));
        }

        return view('publik.verifikasi-form', ['galat' => 'Kode tidak dikenali. Gunakan kode atau tautan pada QR surat.']);
    }
}
