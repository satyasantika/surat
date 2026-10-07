<?php

namespace App\Enums;

enum StatusNaskah: string
{
    case Draf = 'draf';
    case Paraf = 'paraf';
    case MenungguTandaTangan = 'menunggu_tanda_tangan';
    case Ditandatangani = 'ditandatangani';
    case Terbit = 'terbit';
    case Dikembalikan = 'dikembalikan';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguTandaTangan => 'Menunggu tanda tangan',
            default => ucfirst($this->value),
        };
    }

    /** Isi masih boleh diubah penyusun. */
    public function dapatDiubah(): bool
    {
        return in_array($this, [self::Draf, self::Dikembalikan], true);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
