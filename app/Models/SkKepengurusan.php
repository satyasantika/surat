<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

/**
 * @property CarbonImmutable $periode_mulai
 * @property CarbonImmutable $periode_selesai
 */
#[Fillable(['ormawa_id', 'nomor_sk', 'tanggal_sk', 'periode_mulai', 'periode_selesai', 'disahkan_oleh'])]
class SkKepengurusan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'sk_kepengurusan';

    protected function casts(): array
    {
        return ['tanggal_sk' => 'date', 'periode_mulai' => 'date', 'periode_selesai' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $sk) {
            if ($sk->periode_selesai->lt($sk->periode_mulai)) {
                throw ValidationException::withMessages(['periode_selesai' => 'Periode selesai tidak boleh sebelum periode mulai.']);
            }
        });
    }

    protected function namaLog(): string
    {
        return 'sk-kepengurusan';
    }

    /** Status berlaku dihitung dari periode (bukan kolom tersimpan). */
    public function berlaku(?CarbonInterface $tanggal = null): bool
    {
        $tanggal = ($tanggal ?? now())->startOfDay();

        return $this->periode_mulai->startOfDay()->lte($tanggal) && $this->periode_selesai->endOfDay()->gte($tanggal);
    }

    /** @return BelongsTo<Ormawa, $this> */
    public function ormawa(): BelongsTo
    {
        return $this->belongsTo(Ormawa::class);
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
