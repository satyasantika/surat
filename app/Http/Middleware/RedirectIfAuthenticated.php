<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Beranda;
use Illuminate\Http\Request;

/**
 * Pengguna yang sudah masuk lalu membuka /masuk (atau /masuk/google) diarahkan ke tujuan
 * sesuai peran: akun panel ke /admin, akun tanpa panel ke berandanya (App\Support\Beranda).
 * Bawaan Laravel (tanpa override ini) jatuh ke "/" karena app ini tidak punya route "dashboard".
 */
class RedirectIfAuthenticated extends \Illuminate\Auth\Middleware\RedirectIfAuthenticated
{
    protected function redirectTo(Request $request): ?string
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $peranPanel = $user->roles->pluck('name')->diff(User::PERAN_TANPA_PANEL)->isNotEmpty();

        return $peranPanel ? url('admin') : Beranda::url($user);
    }
}
