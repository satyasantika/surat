<?php

namespace App\Models;

use App\Enums\TipeUnitKerja;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public const PERAN_PENGAJU = 'pengaju';

    public const PERAN_PENYETUJU = 'penyetuju';

    /**
     * Akun yang boleh mengajukan permohonan atas nama unit ini.
     *
     * @return BelongsToMany<User, $this>
     */
    public function pengaju(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'penugasan_unit', 'unit_kerja_id', 'user_id')->wherePivot('peran', self::PERAN_PENGAJU)->withPivotValue('peran', self::PERAN_PENGAJU)->withTimestamps();
    }

    /**
     * Kepala unit yang menyetujui permohonan unit ini sebelum masuk ke fakultas.
     *
     * @return BelongsToMany<User, $this>
     */
    public function penyetuju(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'penugasan_unit', 'unit_kerja_id', 'user_id')->wherePivot('peran', self::PERAN_PENYETUJU)->withPivotValue('peran', self::PERAN_PENYETUJU)->withTimestamps();
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
