<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan semua respons web (F12.1): nosniff, bingkai hanya dari asal sama, kebijakan perujuk dan perangkat,
 * HSTS (produksi, HTTPS), serta CSP berbasis nonce untuk halaman aplikasi (skrip inline tanpa nonce diblokir).
 *
 * Panel Filament/Livewire-update/Horizon dikecualikan dari CSP: Filament menyuntikkan skrip dan gaya inline tanpa nonce.
 * Halaman publik, verifikasi, dan pratinjau menetapkan CSP-nya sendiri dan tidak ditimpa. `unsafe-eval` masih dibutuhkan
 * Alpine/Livewire edisi standar; peralihan ke edisi CSP-aman Livewire dicatat sebagai pekerjaan lanjutan (docs/KEAMANAN.md).
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->panel($request) ? null : Vite::useCspNonce();

        $response = $next($request);
        $h = $response->headers;

        $h->has('X-Content-Type-Options') || $h->set('X-Content-Type-Options', 'nosniff');
        $h->has('X-Frame-Options') || $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->has('Referrer-Policy') || $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->has('Permissions-Policy') || $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if (app()->isProduction() && $request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($nonce !== null && ! $h->has('Content-Security-Policy') && str_contains((string) $h->get('Content-Type'), 'text/html')) {
            $h->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https://lh3.googleusercontent.com",
                "font-src 'self' data:",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }

    private function panel(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'livewire', 'livewire/*', 'livewire-*', 'horizon', 'horizon/*', 'filament/*');
    }
}
