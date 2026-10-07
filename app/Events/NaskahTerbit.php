<?php

namespace App\Events;

use App\Models\Naskah;
use Illuminate\Foundation\Events\Dispatchable;

/** Naskah selesai diterbitkan (nomor, hash, status terbit). */
class NaskahTerbit
{
    use Dispatchable;

    public function __construct(public Naskah $naskah) {}
}
