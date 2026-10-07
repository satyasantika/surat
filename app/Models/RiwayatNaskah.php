<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Jejak transisi status naskah. Tidak pernah diubah atau dihapus. */
#[Fillable(['naskah_id', 'dari_status', 'ke_status', 'oleh', 'catatan'])]
class RiwayatNaskah extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'riwayat_naskah';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat naskah tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat naskah tidak dapat dihapus.'));
    }

    /** @return BelongsTo<Naskah, $this> */
    public function naskah(): BelongsTo
    {
        return $this->belongsTo(Naskah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
