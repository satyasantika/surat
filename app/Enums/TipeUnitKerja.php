<?php

namespace App\Enums;

enum TipeUnitKerja: string
{
    case Fakultas = 'fakultas';
    case Prodi = 'prodi';
    case Laboratorium = 'laboratorium';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Fakultas => 'Fakultas',
            self::Prodi => 'Program studi',
            self::Laboratorium => 'Laboratorium',
            self::Lainnya => 'Lainnya',
        };
    }
}
