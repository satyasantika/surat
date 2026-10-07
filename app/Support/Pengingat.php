<?php

namespace App\Support;

use App\Models\PengingatTerkirim;
use Closure;
use Illuminate\Database\QueryException;

/** Menjalankan pengingat tepat sekali per kunci (UNIQUE) walau perintah terjadwal berjalan berulang atau bersamaan. */
class Pengingat
{
    /** @param  Closure(): mixed  $kirim */
    public static function sekali(string $kunci, Closure $kirim): bool
    {
        try {
            PengingatTerkirim::create(['kunci' => $kunci]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE')) {
                return false;
            }

            throw $e;
        }

        $kirim();

        return true;
    }
}
