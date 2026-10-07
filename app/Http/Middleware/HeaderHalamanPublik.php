<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan halaman publik: hanya sumber gambar/video yang dipakai galeri dan kabar yang diizinkan. */
class HeaderHalamanPublik
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Server dev Vite memerlukan sumber tambahan; CSP ketat hanya untuk build produksi.
        if (! file_exists(public_path('hot'))) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "img-src 'self' data: https://lh3.googleusercontent.com https://*.unsil.ac.id",
                'frame-src https://www.youtube-nocookie.com https://drive.google.com',
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
