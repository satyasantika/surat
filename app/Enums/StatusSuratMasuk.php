<?php

namespace App\Enums;

enum StatusSuratMasuk: string
{
    case Diterima = 'diterima';
    case Didisposisikan = 'didisposisikan';
    case Selesai = 'selesai';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
