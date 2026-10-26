<?php

namespace App\Enums;

/** Status usulan (mahasiswa atau kiriman pihak luar) pada perantara (ormawa atau program studi/laboratorium). */
enum StatusUsulan: string
{
    case Diajukan = 'diajukan';
    case Dikembalikan = 'dikembalikan';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';
    case Dipakai = 'dipakai';
    /** Akhir tanpa tindak lanjut; hanya untuk kiriman pihak luar (mis. undangan). */
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu perantara',
            self::Dikembalikan => 'Dikembalikan untuk diperbaiki',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Dipakai => 'Dijadikan dasar permohonan',
            self::Diarsipkan => 'Diarsipkan oleh perantara',
        };
    }

    /** Usulan yang masih dapat diubah atau dibatalkan mahasiswa. */
    public function terbuka(): bool
    {
        return in_array($this, [self::Diajukan, self::Dikembalikan], true);
    }
}
