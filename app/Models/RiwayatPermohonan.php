<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Jejak transisi status permohonan (pengganti JSON steps, S-11). Tidak pernah diubah atau dihapus. */
#[Fillable(['permohonan_id', 'dari_status', 'ke_status', 'oleh', 'pelaku_lama', 'catatan'])]
class RiwayatPermohonan extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'riwayat_permohonan';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat permohonan tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat permohonan tidak dapat dihapus.'));
    }

    /** @return BelongsTo<Permohonan, $this> */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
