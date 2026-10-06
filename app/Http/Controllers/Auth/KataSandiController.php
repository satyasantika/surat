<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as AturanSandi;
use Illuminate\View\View;

class KataSandiController extends Controller
{
    public function request(): View
    {
        return view('auth.lupa-sandi');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Jawaban sama untuk surel terdaftar maupun tidak (tidak membocorkan akun).
        return back()->with('status', 'Bila surel terdaftar, tautan pengaturan ulang telah dikirim.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.atur-ulang-sandi', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', AturanSandi::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ]);
            // Tautan terkirim ke surel ini, jadi kepemilikan surel terbukti.
            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }
            $user->save();

            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', 'Kata sandi diperbarui. Silakan masuk.')
            : back()->withErrors(['email' => 'Tautan tidak valid atau kedaluwarsa.']);
    }
}
