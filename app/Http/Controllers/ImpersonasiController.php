<?php

namespace App\Http\Controllers;

use App\Actions\Pengguna\MulaiImpersonasi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fitur resmi "masuk sebagai" (BR-22): hanya super-admin, tercatat di log aktivitas.
 */
class ImpersonasiController extends Controller
{
    public function mulai(Request $request, User $user, MulaiImpersonasi $aksi): RedirectResponse
    {
        $aksi->jalankan($request->user(), $user);

        return redirect(url('admin'));
    }

    public function selesai(Request $request): RedirectResponse
    {
        $asliId = $request->session()->get('impersonator_id');
        abort_unless($asliId, 403);

        $dipakai = $request->user();
        $admin = User::findOrFail($asliId);

        $request->session()->forget('impersonator_id');
        // Pelaku asli (bukan akun yang sedang ditiru) menjadi causer entri penutup.
        activity('impersonasi')->causedBy($admin)->event('selesai')->performedOn($dipakai)
            ->log("Selesai masuk sebagai {$dipakai->email}");

        $request->session()->regenerate();
        Auth::login($admin);
        $request->session()->forget('password_hash_web');

        return redirect(url('admin'));
    }
}
