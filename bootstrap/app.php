<?php

use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // APP_URL sudah memuat subpath /surat secara eksplisit; X-Forwarded-Prefix dari proksi fakultas
        // tidak diikutkan agar basis URL (dipakai Livewire utk endpoint update) tidak berganda jadi /surat/surat/...
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Pengguna yang sudah masuk lalu membuka /masuk diarahkan ke tujuan sesuai peran (bukan ke beranda publik).
        $middleware->alias(['guest' => RedirectIfAuthenticated::class]);

        // Global (bukan grup web) agar panel Filament, halaman 404, dan unduhan juga memperoleh header dasar.
        $middleware->append(HeaderKeamanan::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
