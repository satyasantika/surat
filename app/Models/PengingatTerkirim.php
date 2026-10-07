<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Penanda pengingat terjadwal yang sudah terkirim (kunci unik). */
class PengingatTerkirim extends Model
{
    use HasUuids;

    protected $table = 'pengingat_terkirim';

    protected $guarded = [];
}
