<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Kode lacak publik (BR-27): 12 karakter acak dari 32 simbol tanpa huruf/angka yang mudah tertukar (I, O, 0, 1),
 * sekitar 60 bit entropi. Bukan UUID/ID internal dan tidak berurutan. Disimpan tanpa tanda hubung, ditampilkan
 * berkelompok empat (XXXX-XXXX-XXXX).
 */
class KodeLacak
{
    public const ALFABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const PANJANG = 12;

    /**
     * @param  Closure(string): bool  $sudahAda  mengembalikan true bila kode sudah dipakai
     */
    public static function baru(Closure $sudahAda): string
    {
        for ($i = 0; $i < 20; $i++) {
            $kode = '';

            for ($j = 0; $j < self::PANJANG; $j++) {
                $kode .= self::ALFABET[random_int(0, strlen(self::ALFABET) - 1)];
            }

            if (! $sudahAda($kode)) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode lacak unik.');
    }

    /** Bentuk simpan dari masukan pengguna (huruf besar, tanpa pemisah); null bila bentuknya tidak mungkin sah. */
    public static function normalisasi(string $masukan): ?string
    {
        $bersih = strtoupper(preg_replace('/[\s-]+/', '', Str::limit($masukan, 60, '')) ?? '');

        return strlen($bersih) === self::PANJANG && strspn($bersih, self::ALFABET) === self::PANJANG ? $bersih : null;
    }

    public static function tampil(string $kode): string
    {
        return implode('-', str_split($kode, 4));
    }
}
