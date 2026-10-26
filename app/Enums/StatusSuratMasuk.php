<?php

namespace App\Enums;

enum StatusSuratMasuk: string
{
    /** Kiriman pengirim eksternal yang belum diterima fakultas: belum bernomor agenda dan belum diregistrasi. */
    case Diajukan = 'diajukan';
    case Diterima = 'diterima';
    case Didisposisikan = 'didisposisikan';
    case Selesai = 'selesai';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan pengirim',
            default => ucfirst($this->value),
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
