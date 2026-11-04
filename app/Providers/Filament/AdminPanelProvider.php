<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfil;
use App\Filament\Pages\Auth\Masuk;
use App\Http\Middleware\PaksaGantiSandi;
use App\Http\Middleware\WajibMfa;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Masuk::class)
            ->passwordReset()
            ->profile(EditProfil::class)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                // Tetap true agar rute penyiapan MFA dan middleware WajibMfa terpasang saat rute didaftarkan (juga saat route:cache);
                // keputusan wajib atau tidak diambil per permintaan oleh WajibMfa lewat User::wajibMfa() dan saklar mfa_wajib.
                // Akun demo panduan (lokal) dapat dikecualikan (config panduan.tanpa_mfa hanya berlaku di local).
                isRequired: ! (config('panduan.tanpa_mfa') && app()->environment('local')),
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(WajibMfa::class)
            ->databaseNotifications()
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('components.banner-impersonasi')->render())
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => view('auth.tombol-google', ['tautan' => route('login.google', ['dari' => 'admin']), 'galat' => session('galat_google')])->render())
            ->renderHook(PanelsRenderHook::FOOTER, fn (): string => '<div class="py-2 text-center text-xs text-gray-500">Persuratan FKIP Unsil v'.e(config('app.version')).' · <a class="underline" href="'.e(asset('panduan/index.html')).'">Panduan pengguna</a></div>')
            ->colors([
                'primary' => Color::hex('#1e3a8a'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                PaksaGantiSandi::class,
            ]);
    }
}
