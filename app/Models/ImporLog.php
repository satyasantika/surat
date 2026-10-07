<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Jejak setiap baris sumber OrmawaHub (07-MIGRASI-DATA §1): status ok/lewati/galat/peringatan. */
class ImporLog extends Model
{
    use HasUuids;

    public const OK = 'ok';

    public const LEWATI = 'lewati';

    public const GALAT = 'galat';

    public const PERINGATAN = 'peringatan';

    protected $table = 'impor_ormawahub_log';

    protected $guarded = [];
}
