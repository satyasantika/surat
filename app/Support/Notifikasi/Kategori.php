<?php

namespace App\Support\Notifikasi;

/** Kategori notifikasi (dasar preferensi per pengguna). */
class Kategori
{
    public const SEMUA = [
        'surat-masuk' => 'Surat masuk perlu disposisi',
        'disposisi' => 'Disposisi',
        'naskah' => 'Naskah (paraf, tanda tangan, terbit)',
        'permohonan' => 'Permohonan ormawa',
        'lpj' => 'LPJ',
        'kabar' => 'Kabar',
        'pengingat' => 'Pengingat tenggat',
    ];

    public static function sah(string $kategori): bool
    {
        return array_key_exists($kategori, self::SEMUA);
    }
}
