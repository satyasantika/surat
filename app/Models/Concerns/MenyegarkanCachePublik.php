<?php

namespace App\Models\Concerns;

use App\Support\CachePublik;

/** Model yang tampil di halaman publik: perubahan apa pun menyegarkan cache halaman publik. */
trait MenyegarkanCachePublik
{
    protected static function bootMenyegarkanCachePublik(): void
    {
        $segarkan = fn () => CachePublik::segarkan();

        static::saved($segarkan);
        static::deleted($segarkan);
    }
}
