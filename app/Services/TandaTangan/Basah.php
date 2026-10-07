<?php

namespace App\Services\TandaTangan;

use App\Contracts\PenandaTangan;
use App\Models\Naskah;
use App\Models\User;

/** PDF dicetak, ditandatangani basah, lalu tautan pindaian dicatat (F5.4). */
class Basah implements PenandaTangan
{
    public function mode(): string
    {
        return 'basah';
    }

    public function siapkan(Naskah $naskah, User $penandatangan): array
    {
        return ['mode' => 'basah'];
    }
}
