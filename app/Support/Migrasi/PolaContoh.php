<?php

namespace App\Support\Migrasi;

/** Pola data contoh sisa templat OrmawaHub yang tidak diimpor (S-13). */
class PolaContoh
{
    /** Pola tingkat baris: satu kecocokan membuat seluruh baris dilewati. */
    private const POLA_BARIS = ['fakultas teknik', 'bem ft', 'ukm robotik', '@ormawahub.ac.id'];

    /** Pola tingkat kolom: hanya kolom berisi pola ini yang dibuang (mis. foto stok). */
    private const POLA_KOLOM = ['unsplash.com', 'images.unsplash'];

    /** @param  array<string, mixed>  $baris */
    public static function barisContoh(array $baris): bool
    {
        foreach ($baris as $nilai) {
            if (is_string($nilai) && self::mengandung($nilai, self::POLA_BARIS)) {
                return true;
            }
        }

        return false;
    }

    public static function kolomContoh(?string $nilai): bool
    {
        return $nilai !== null && self::mengandung($nilai, [...self::POLA_BARIS, ...self::POLA_KOLOM]);
    }

    /** @param  list<string>  $pola */
    private static function mengandung(string $nilai, array $pola): bool
    {
        $kecil = mb_strtolower($nilai);

        foreach ($pola as $p) {
            if (str_contains($kecil, $p)) {
                return true;
            }
        }

        return false;
    }
}
