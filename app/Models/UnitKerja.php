<?php

namespace App\Models;

use App\Enums\TipeUnitKerja;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'tipe', 'induk_id', 'aktif'])]
class UnitKerja extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'unit_kerja';

    protected $attributes = ['aktif' => true, 'tipe' => 'lainnya'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'tipe' => TipeUnitKerja::class];
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /** @return HasMany<UnitKerja, $this> */
    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    /** @return HasMany<Jabatan, $this> */
    public function jabatan(): HasMany
    {
        return $this->hasMany(Jabatan::class);
    }
}
