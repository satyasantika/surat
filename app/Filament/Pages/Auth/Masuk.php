<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman masuk panel dua kolom (panel merek di kiri, form di kanan). Form, validasi, tantangan MFA, dan tombol
 * Google (render hook) tetap milik Filament; hanya layout dan subjudul yang diganti.
 */
class Masuk extends Login
{
    protected static string $layout = 'filament.auth.layout-masuk';

    public function hasLogo(): bool
    {
        // Merek sudah tampil di panel kiri (desktop) dan bilah atas (ponsel).
        return false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Halaman masuk pimpinan, staf, dan admin persuratan.';
    }
}
