<?php

namespace App\Enums;

/** Pihak yang mengajukan permohonan: ormawa (via pembina), program studi, atau laboratorium (via kepala unit). */
enum JenisPengaju: string
{
    case Ormawa = 'ormawa';
    case Prodi = 'prodi';
    case Laboratorium = 'laboratorium';

    public function label(): string
    {
        return match ($this) {
            self::Ormawa => 'Ormawa',
            self::Prodi => 'Program studi',
            self::Laboratorium => 'Laboratorium',
        };
    }

    /** Pengaju yang diwakili unit kerja (bukan ormawa). */
    public function berunit(): bool
    {
        return $this !== self::Ormawa;
    }
}
