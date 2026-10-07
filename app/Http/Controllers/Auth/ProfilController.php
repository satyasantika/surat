<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Notifikasi\SimpanPreferensiNotifikasi;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfilController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.profil', ['user' => $request->user()]);
    }

    public function updateNotifikasi(Request $request, SimpanPreferensiNotifikasi $simpan): RedirectResponse
    {
        $simpan->jalankan($request->user(), $request->only(['telepon', 'pref']));

        return back()->with('status', 'Preferensi notifikasi disimpan.');
    }

    public function updateSandi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers(), 'different:current_password'],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', 'Kata sandi diperbarui. Perangkat lain telah dikeluarkan.');
    }
}
