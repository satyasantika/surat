<?php

namespace App\Support\Migrasi;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/** Pengurai tanggal campuran OrmawaHub: objek tanggal Excel, nomor seri Excel, ISO, dan "15 Jul 2026" (locale id). */
class PenguraiTanggal
{
    private const BULAN = [
        'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'pebruari' => 2, 'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'aug' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'nopember' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12,
    ];

    public static function urai(mixed $nilai): ?CarbonImmutable
    {
        if ($nilai instanceof DateTimeInterface) {
            return CarbonImmutable::instance($nilai)->startOfDay();
        }

        if (is_int($nilai) || is_float($nilai) || (is_string($nilai) && preg_match('/^\d{5}(\.\d+)?$/', trim($nilai)) === 1)) {
            $seri = (float) $nilai;

            return $seri >= 20000 && $seri <= 80000 ? CarbonImmutable::create(1899, 12, 30)->addDays((int) floor($seri))->startOfDay() : null;
        }

        if (! is_string($nilai) || trim($nilai) === '') {
            return null;
        }

        $teks = trim($nilai);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ].*)?$/', $teks, $m) === 1) {
            return self::buat((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})[\s\-\/.]+([A-Za-z]+)\.?[\s\-\/.,]+(\d{4})$/u', $teks, $m) === 1) {
            $bulan = self::BULAN[strtolower($m[2])] ?? null;

            return $bulan === null ? null : self::buat((int) $m[3], $bulan, (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $teks, $m) === 1) {
            return self::buat((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private static function buat(int $tahun, int $bulan, int $hari): ?CarbonImmutable
    {
        if ($tahun < 1990 || $tahun > 2100 || ! checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        try {
            return CarbonImmutable::create($tahun, $bulan, $hari)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
