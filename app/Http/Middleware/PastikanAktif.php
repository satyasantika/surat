<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Mengeluarkan pengguna yang dinonaktifkan saat sesinya masih berjalan. */
class PastikanAktif
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user() && ! $request->user()->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
