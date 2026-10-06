<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'pola', 'reset', 'aktif'])]
class RegisterNomor extends Model
{
    use HasUuids, TercatatAktivitas;

    public const RESET = ['tahunan' => 'Tiap tahun kalender', 'tidak' => 'Tidak direset'];

    protected $table = 'register_nomor';

    protected $attributes = ['reset' => 'tahunan', 'aktif' => true];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** @return HasMany<NomorTerpakai, $this> */
    public function nomor(): HasMany
    {
        return $this->hasMany(NomorTerpakai::class);
    }
}
