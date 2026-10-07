<?php

namespace App\Services\Laporan;

use App\Enums\KlasifikasiKeamanan;
use Carbon\CarbonImmutable;

/**
 * Laporan persuratan dan layanan ormawa (PRD §9 LAP-01–LAP-05). Hasil berupa daftar tabel (judul, kolom, baris).
 * Laporan hanya memuat data organisasi: tidak ada NIM, telepon, atau surel pribadi, dan perihal non-biasa dirahasiakan.
 */
abstract class Laporan
{
    abstract public function kode(): string;

    abstract public function judul(): string;

    abstract public function deskripsi(): string;

    /** @return list<array{judul: string, kolom: list<string>, baris: list<list<scalar|null>>}> */
    abstract public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array;

    protected function perihalAman(KlasifikasiKeamanan $klasifikasi, string $perihal): string
    {
        return $klasifikasi === KlasifikasiKeamanan::Biasa ? $perihal : '(dirahasiakan: '.$klasifikasi->label().')';
    }

    protected static function hari(float|int|null $detik): float
    {
        return round(((float) $detik) / 86400, 1);
    }
}
