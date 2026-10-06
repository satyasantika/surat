<?php

namespace App\Enums;

enum StatusDisposisiPenerima: string
{
    case Diterima = 'diterima';
    case Dibaca = 'dibaca';
    case Ditindaklanjuti = 'ditindaklanjuti';
    case Selesai = 'selesai';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
