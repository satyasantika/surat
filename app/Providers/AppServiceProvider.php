<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
