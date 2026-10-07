<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['naskah_id', 'user_id', 'jabatan_id', 'urutan', 'status', 'catatan', 'diputus_pada'])]
class NaskahParaf extends Model
{
    use HasUuids;

    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DIKEMBALIKAN = 'dikembalikan';

    protected $table = 'naskah_paraf';

    protected $attributes = ['status' => 'menunggu'];

    protected function casts(): array
    {
        return ['diputus_pada' => 'datetime', 'urutan' => 'integer'];
    }

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

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }
}
