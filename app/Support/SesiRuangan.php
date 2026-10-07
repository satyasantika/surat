<?php

namespace App\Support;

/** Sesi pemakaian ruangan dari pengaturan; "seharian" bentrok dengan sesi lain pada tanggal yang sama. */
class SesiRuangan
{
    public const SEHARIAN = 'seharian';

    /** @return list<string> */
    public static function kode(): array
    {
        return array_values(array_map(fn (array $s) => $s['kode'], Pengaturan::get('sesi')));
    }

    public static function valid(string $sesi): bool
    {
        return in_array($sesi, self::kode(), true);
    }

    public static function bentrok(string $a, string $b): bool
    {
        return $a === $b || $a === self::SEHARIAN || $b === self::SEHARIAN;
    }

    /** Semua sesi yang bentrok dengan $sesi (termasuk dirinya). */
    /** @return list<string> */
    public static function yangBentrok(string $sesi): array
    {
        return array_values(array_filter(self::kode(), fn (string $k) => self::bentrok($sesi, $k)));
    }
}
