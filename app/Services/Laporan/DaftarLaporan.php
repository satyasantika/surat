<?php

namespace App\Services\Laporan;

/** Daftar laporan yang tersedia. */
class DaftarLaporan
{
    /** @return array<string, Laporan> kode => laporan */
    public static function semua(): array
    {
        $daftar = [new Lap01Register, new Lap02Disposisi, new Lap03Permohonan, new Lap04Lpj, new Lap05Ruangan, new Lap06Retensi];

        return array_combine(array_map(fn (Laporan $l) => $l->kode(), $daftar), $daftar);
    }

    public static function cari(string $kode): ?Laporan
    {
        return self::semua()[$kode] ?? null;
    }
}
