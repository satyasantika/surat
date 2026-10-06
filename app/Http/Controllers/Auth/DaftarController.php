<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Pendaftaran mandiri pengurus ormawa: hanya surel domain mahasiswa dan wajib verifikasi surel.
 * Akun belum terhubung ke ormawa sampai ditautkan admin (F6).
 */
class DaftarController extends Controller
{
    public function create(): View
    {
        return view('auth.daftar');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'nip_nim' => ['required', 'string', 'max:30', 'unique:users,nip_nim'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', new SurelDomainUnsil(config('unsil.domain_mahasiswa'))],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create($data);
        $user->assignRole('pengurus-ormawa');

        event(new Registered($user));

        return redirect()->route('login')
            ->with('status', 'Pendaftaran berhasil. Kami mengirim tautan verifikasi ke surel Anda.');
    }

    public function verifikasi(Request $request, string $id, string $hash): RedirectResponse
    {
        abort_unless(URL::hasValidSignature($request), 403);

        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('login')->with('status', 'Surel terverifikasi. Silakan masuk.');
    }
}
