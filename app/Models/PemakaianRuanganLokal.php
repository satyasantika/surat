<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use App\Support\SesiRuangan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** @property CarbonImmutable $tanggal */
#[Fillable(['ruangan_lokal_id', 'tanggal', 'sesi', 'keterangan', 'permohonan_id'])]
class PemakaianRuanganLokal extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'pemakaian_ruangan_lokal';

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    protected function namaLog(): string
    {
        return 'pemakaian-ruangan';
    }

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            if (! SesiRuangan::valid($p->sesi)) {
                throw ValidationException::withMessages(['sesi' => 'Sesi tidak dikenal.']);
            }
        });
    }

    /** @return BelongsTo<RuanganLokal, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(RuanganLokal::class, 'ruangan_lokal_id');
    }
}
