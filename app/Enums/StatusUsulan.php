<?php

namespace App\Enums;

/** Status usulan mahasiswa pada perantara (ormawa atau program studi/laboratorium). */
enum StatusUsulan: string
{
    case Diajukan = 'diajukan';
    case Dikembalikan = 'dikembalikan';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';
    case Dipakai = 'dipakai';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu perantara',
            self::Dikembalikan => 'Dikembalikan untuk diperbaiki',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Dipakai => 'Dijadikan dasar permohonan',
        };
    }

    /** Usulan yang masih dapat diubah atau dibatalkan mahasiswa. */
    public function terbuka(): bool
    {
        return in_array($this, [self::Diajukan, self::Dikembalikan], true);
    }
}
