<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman masuk untuk pengurus ormawa dan pegawai. Akun panel (pejabat, admin) masuk lewat /admin
 * agar tidak melewati tantangan MFA.
 */
class MasukController extends Controller
{
    public function create(): View
    {
        return view('auth.masuk');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $gagal = ValidationException::withMessages(['email' => 'Surel atau kata sandi salah.']);

        if (! Auth::validate(['email' => $data['email'], 'password' => $data['password']])) {
            throw $gagal;
        }

        /** @var User $user */
        $user = User::where('email', $data['email'])->firstOrFail();

        if (! $user->aktif) {
            throw $gagal;
        }

        if ($user->roles->pluck('name')->diff(User::PERAN_TANPA_PANEL)->isNotEmpty()) {
            throw ValidationException::withMessages(['email' => 'Akun ini masuk melalui halaman admin.']);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Surel belum diverifikasi. Periksa kotak masuk Anda.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('profil'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
