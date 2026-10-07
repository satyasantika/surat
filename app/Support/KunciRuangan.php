<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kunci per ruangan+tanggal (mencakup semua sesi, karena "seharian" bentrok dengan pagi/siang). Pada
 * MariaDB/MySQL dipakai GET_LOCK (berlaku lintas proses tanpa bergantung pada penyimpanan cache); selain itu
 * Cache::lock. Beberapa kunci diambil berurutan menurut nama agar dua pengajuan paralel tidak saling deadlock.
 */
class KunciRuangan
{
    /**
     * @template T
     *
     * @param  list<array{kode: string, tanggal: string}>  $butir
     * @param  Closure(): T  $kerja
     * @return T
     */
    public static function dengan(array $butir, Closure $kerja): mixed
    {
        $nama = collect($butir)->map(fn (array $b) => "ruangan:{$b['kode']}:{$b['tanggal']}")->unique()->sort()->values()->all();

        return self::ambil($nama, 0, $kerja);
    }

    /**
     * @template T
     *
     * @param  list<string>  $nama
     * @param  Closure(): T  $kerja
     * @return T
     */
    private static function ambil(array $nama, int $i, Closure $kerja): mixed
    {
        if ($i >= count($nama)) {
            return $kerja();
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $diperoleh = DB::selectOne('SELECT GET_LOCK(?, 10) AS k', [$nama[$i]])->k ?? null;

            if ((int) $diperoleh !== 1) {
                throw new RuntimeException("Gagal mengunci {$nama[$i]}; coba lagi.");
            }

            try {
                return self::ambil($nama, $i + 1, $kerja);
            } finally {
                DB::select('SELECT RELEASE_LOCK(?)', [$nama[$i]]);
            }
        }

        return Cache::lock($nama[$i], 15)->block(10, fn () => self::ambil($nama, $i + 1, $kerja));
    }
}
