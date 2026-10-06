<?php

namespace App\Enums;

enum KlasifikasiKeamanan: string
{
    case Biasa = 'biasa';
    case Terbatas = 'terbatas';
    case Rahasia = 'rahasia';
    case SangatRahasia = 'sangat_rahasia';

    public function label(): string
    {
        return match ($this) {
            self::Biasa => 'Biasa',
            self::Terbatas => 'Terbatas',
            self::Rahasia => 'Rahasia',
            self::SangatRahasia => 'Sangat rahasia',
        };
    }

    /** Akses isi dibatasi (BR-04). */
    public function tertutup(): bool
    {
        return in_array($this, [self::Rahasia, self::SangatRahasia], true);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
