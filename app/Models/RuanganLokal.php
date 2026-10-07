<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'gedung', 'kapasitas', 'fasilitas', 'dalam_perawatan', 'tampil_katalog', 'sumber_id_lama'])]
class RuanganLokal extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'ruangan_lokal';

    protected $attributes = ['dalam_perawatan' => false, 'tampil_katalog' => true];

    protected function casts(): array
    {
        return ['dalam_perawatan' => 'boolean', 'tampil_katalog' => 'boolean', 'kapasitas' => 'integer'];
    }

    protected function namaLog(): string
    {
        return 'ruangan-lokal';
    }

    /** @return HasMany<PemakaianRuanganLokal, $this> */
    public function pemakaian(): HasMany
    {
        return $this->hasMany(PemakaianRuanganLokal::class);
    }
}
