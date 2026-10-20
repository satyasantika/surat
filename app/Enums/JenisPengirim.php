<?php

namespace App\Enums;

enum JenisPengirim: string
{
    case Ormawa = 'ormawa';
    case Prodi = 'prodi';
    case Laboratorium = 'laboratorium';
    case Eksternal = 'eksternal';

    public function label(): string
    {
        return match ($this) {
            self::Ormawa => 'Ormawa',
            self::Prodi => 'Program studi',
            self::Laboratorium => 'Laboratorium',
            self::Eksternal => 'Eksternal (UKM/umum)',
        };
    }

    /** Pengirim resmi yang diwakili unit kerja (wajib memilih unit). */
    public function berunit(): bool
    {
        return $this === self::Prodi || $this === self::Laboratorium;
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
