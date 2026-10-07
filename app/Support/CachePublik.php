<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache halaman publik (HTML jadi) maksimal 5 menit. Setiap perubahan model yang tampil publik menaikkan
 * versi sehingga konten baru langsung tampil dan konten yang ditarik (mis. kabar dibatalkan) tidak bertahan.
 */
class CachePublik
{
    public const DETIK = 300;

    /** @param  \Closure(): string  $render */
    public static function ingat(string $kunci, \Closure $render): string
    {
        return Cache::remember('publik:'.self::versi().':'.$kunci, self::DETIK, $render);
    }

    public static function segarkan(): void
    {
        Cache::forever('publik:versi', self::versi() + 1);
    }

    private static function versi(): int
    {
        return (int) Cache::get('publik:versi', 1);
    }
}
