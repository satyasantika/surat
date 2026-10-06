<?php

namespace App\Contracts;

use App\Exceptions\ModeTidakDidukung;
use App\Models\Naskah;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Mode tanda tangan (BR-09). Mengembalikan keterangan mode untuk snapshot, atau menolak bila syarat belum terpenuhi. */
interface PenandaTangan
{
    public function mode(): string;

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException bila syarat mode tidak terpenuhi
     * @throws ModeTidakDidukung bila mode belum tersedia
     */
    public function siapkan(Naskah $naskah, User $penandatangan): array;
}
