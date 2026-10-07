<?php

namespace App\Support\Migrasi;

use DateTimeInterface;

/** Pembersih nilai sel XLSX: semua sel dibaca sebagai teks terpangkas; kosong = null. */
class Sel
{
    public static function teks(mixed $nilai): ?string
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_float($nilai) && floor($nilai) === $nilai) {
            $nilai = (int) $nilai;
        }

        if (is_bool($nilai)) {
            return $nilai ? 'TRUE' : 'FALSE';
        }

        $t = trim((string) $nilai);

        return $t === '' ? null : $t;
    }

    public static function bool(mixed $nilai, bool $bawaan = false): bool
    {
        $t = self::teks($nilai);

        return $t === null ? $bawaan : in_array(strtolower($t), ['true', '1', 'ya', 'y', 'yes'], true);
    }

    public static function int(mixed $nilai): ?int
    {
        $t = self::teks($nilai);

        return $t !== null && preg_match('/^\d{1,9}$/', $t) === 1 ? (int) $t : null;
    }

    /** Telepon: hanya digit/+/-/spasi/kurung, maksimum 20; selain itu null. */
    public static function telepon(mixed $nilai): ?string
    {
        $t = self::teks($nilai);

        return $t !== null && strlen($t) <= 20 && preg_match('/^[0-9+\-\s()]+$/', $t) === 1 ? $t : null;
    }
}
