<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama', 'unit_kerja_id', 'bidang', 'dapat_menandatangani', 'urutan'])]
class Jabatan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'jabatan';

    protected function casts(): array
    {
        return ['dapat_menandatangani' => 'boolean', 'urutan' => 'integer'];
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /** @return HasMany<PemangkuJabatan, $this> */
    public function pemangku(): HasMany
    {
        return $this->hasMany(PemangkuJabatan::class);
    }

    /**
     * Pemangku yang berlaku pada tanggal tertentu. Bila ada Plt/definitif yang berlaku bersamaan,
     * dipilih yang mulai paling akhir; pada tanggal mulai yang sama, Plt didahulukan.
     */
    public function pemangkuPada(CarbonInterface $tanggal): ?PemangkuJabatan
    {
        return $this->pemangku()
            ->berlakuPada($tanggal)
            ->with('user')
            ->orderByDesc('mulai')
            ->orderByDesc('plt')
            ->first();
    }
}
