<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum DerajatKecepatan: string
{
    case SangatSegera = 'sangat_segera';
    case Segera = 'segera';
    case Biasa = 'biasa';

    public function label(): string
    {
        return match ($this) {
            self::SangatSegera => 'Sangat segera',
            self::Segera => 'Segera',
            self::Biasa => 'Biasa',
        };
    }

    /** Batas waktu bawaan (RS-04, BR-05): hari yang sama 23:59, +48 jam, +7 hari. */
    public function batasWaktu(CarbonInterface $dari): CarbonInterface
    {
        return match ($this) {
            self::SangatSegera => $dari->copy()->endOfDay()->startOfMinute(),
            self::Segera => $dari->copy()->addHours(48),
            self::Biasa => $dari->copy()->addDays(7),
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
