<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Pejabat dan admin (config unsil.peran_wajib_mfa) wajib mengaktifkan MFA sebelum memakai panel.
 */
class WajibMfa
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        if ($user === null || ! $user->wajibMfa() || MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
