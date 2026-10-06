<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Nomor yang sudah dikeluarkan. Tidak dapat diubah (kecuali ditandai batal) maupun dihapus:
 * nomor batal tidak dipakai ulang (BR-06).
 */
#[Fillable(['register_nomor_id', 'tahun', 'urut', 'nomor_lengkap', 'pemilik_type', 'pemilik_id', 'dibatalkan', 'dibuat_oleh'])]
class NomorTerpakai extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'nomor_terpakai';

    protected function casts(): array
    {
        return ['dibatalkan' => 'boolean', 'tahun' => 'integer', 'urut' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $nomor) {
            $diubah = array_diff(array_keys($nomor->getDirty()), ['dibatalkan', 'updated_at']);

            if ($diubah !== [] || ($nomor->isDirty('dibatalkan') && ! $nomor->dibatalkan)) {
                throw new LogicException('Nomor terpakai tidak dapat diubah; hanya dapat dibatalkan.');
            }
        });

        static::deleting(fn () => throw new LogicException('Nomor terpakai tidak dapat dihapus.'));
    }

    /** @return BelongsTo<RegisterNomor, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(RegisterNomor::class, 'register_nomor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function pemilik(): MorphTo
    {
        return $this->morphTo();
    }
}
