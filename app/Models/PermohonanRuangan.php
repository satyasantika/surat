<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable $tanggal */
#[Fillable(['permohonan_id', 'kode_ruangan', 'nama_ruangan', 'tanggal', 'sesi', 'status', 'id_pemakaian_aset'])]
class PermohonanRuangan extends Model
{
    use HasUuids, TercatatAktivitas;

    public const DITAHAN = 'ditahan';

    public const DIKONFIRMASI = 'dikonfirmasi';

    public const DILEPAS = 'dilepas';

    protected $table = 'permohonan_ruangan';

    protected $attributes = ['status' => 'ditahan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    protected function namaLog(): string
    {
        return 'permohonan-ruangan';
    }

    /**
     * Baris yang masih menguasai ruangan (ditahan/dikonfirmasi).
     *
     * @param  Builder<PermohonanRuangan>  $query
     */
    public function scopeMenguasai(Builder $query): void
    {
        $query->whereIn('status', [self::DITAHAN, self::DIKONFIRMASI]);
    }

    /** @return BelongsTo<Permohonan, $this> */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }
}
