<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['naskah_id', 'jenis', 'nama', 'user_id', 'urutan'])]
class NaskahTujuan extends Model
{
    use HasUuids;

    protected $table = 'naskah_tujuan';

    /** @return BelongsTo<Naskah, $this> */
    public function naskah(): BelongsTo
    {
        return $this->belongsTo(Naskah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
