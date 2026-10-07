<?php

namespace App\Services\TandaTangan;

use App\Contracts\PenandaTangan;
use App\Exceptions\ModeTidakDidukung;
use App\Models\Naskah;
use App\Models\User;

/** TTE tersertifikasi (BSrE) — fase lanjutan; belum tersedia. */
class Tte implements PenandaTangan
{
    public function mode(): string
    {
        return 'tte';
    }

    public function siapkan(Naskah $naskah, User $penandatangan): array
    {
        throw new ModeTidakDidukung('Mode TTE tersertifikasi belum tersedia.');
    }
}
