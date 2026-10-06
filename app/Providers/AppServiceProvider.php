<?php

namespace App\Providers;

use App\Contracts\PembangkitPdf;
use App\Contracts\PenyimpananBerkas;
use App\Services\Berkas\TautanEksternal;
use App\Services\Pdf\PembangkitPdfDompdf;
use App\Services\Pdf\PembangkitPdfGotenberg;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PenyimpananBerkas::class, fn () => match (config('berkas.mode')) {
            default => new TautanEksternal,
        });

        $this->app->bind(PembangkitPdf::class, fn () => $this->pembangkitPdf((string) config('pdf.driver')));
        $this->app->bind('pdf.naskah', fn () => $this->pembangkitPdf((string) config('pdf.driver_naskah')));
    }

    private function pembangkitPdf(string $driver): PembangkitPdf
    {
        return match ($driver) {
            'dompdf' => new PembangkitPdfDompdf,
            default => new PembangkitPdfGotenberg((string) config('pdf.gotenberg_url')),
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Carbon::setLocale('id');

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        RateLimiter::for('masuk', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('daftar', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Hanya super-admin (lewat Gate::before) yang boleh masuk sebagai pengguna lain.
        Gate::define('impersonasi', fn () => false);

        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);

        $this->aturUrlSubpath();
    }

    /**
     * Aplikasi dapat berjalan di bawah awalan path (mis. /surat); semua URL ikut APP_URL.
     */
    protected function aturUrlSubpath(): void
    {
        $url = (string) config('app.url');
        $path = parse_url($url, PHP_URL_PATH);

        if ($path !== null && $path !== '' && $path !== '/') {
            URL::forceRootUrl($url);
        }

        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
    }
}
