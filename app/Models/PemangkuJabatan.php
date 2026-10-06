<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * @property CarbonImmutable $mulai
 * @property CarbonImmutable|null $selesai
 */
#[Fillable(['jabatan_id', 'user_id', 'mulai', 'selesai', 'plt', 'nomor_sk'])]
class PemangkuJabatan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'pemangku_jabatan';

    protected function casts(): array
    {
        return ['mulai' => 'date', 'selesai' => 'date', 'plt' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $pemangku) {
            if ($pemangku->selesai !== null && $pemangku->selesai->lt($pemangku->mulai)) {
                throw ValidationException::withMessages(['selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
            }

            if (! $pemangku->plt && $pemangku->tumpangTindihDefinitif()) {
                throw ValidationException::withMessages([
                    'mulai' => 'Jabatan ini sudah memiliki pemangku definitif pada rentang tanggal tersebut.',
                ]);
            }
        });
    }

    /** @param  Builder<PemangkuJabatan>  $query */
    public function scopeBerlakuPada(Builder $query, CarbonInterface $tanggal): void
    {
        $tanggal = $tanggal->toDateString();

        $query->whereDate('mulai', '<=', $tanggal)
            ->where(fn (Builder $q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $tanggal));
    }

    private function tumpangTindihDefinitif(): bool
    {
        return static::query()
            ->where('jabatan_id', $this->jabatan_id)
            ->where('plt', false)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            // rentang [mulai, selesai] bertumpuk; selesai null = tanpa batas
            ->where(fn (Builder $q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $this->mulai->toDateString()))
            ->when($this->selesai, fn (Builder $q) => $q->whereDate('mulai', '<=', $this->selesai->toDateString()))
            ->exists();
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
