<?php

namespace App\Models;

use App\Enums\StatusDisposisiPenerima;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property StatusDisposisiPenerima $status */
#[Fillable(['disposisi_id', 'user_id', 'jabatan_id', 'status', 'dibaca_pada', 'ditindaklanjuti_pada', 'selesai_pada', 'laporan_tindak_lanjut', 'terlambat'])]
class DisposisiPenerima extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'disposisi_penerima';

    protected $attributes = ['status' => 'diterima', 'terlambat' => false];

    protected function casts(): array
    {
        return [
            'status' => StatusDisposisiPenerima::class,
            'dibaca_pada' => 'datetime',
            'ditindaklanjuti_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'terlambat' => 'boolean',
        ];
    }

    /**
     * Belum selesai dan lewat batas waktu disposisinya.
     *
     * @param  Builder<DisposisiPenerima>  $query
     */
    public function scopeLewatBatas(Builder $query): void
    {
        $query->where('status', '!=', StatusDisposisiPenerima::Selesai->value)
            ->whereHas('disposisi', fn (Builder $q) => $q->where('batas_waktu', '<', now()));
    }

    public function sudahLewatBatas(): bool
    {
        $this->loadMissing('disposisi');

        return $this->status !== StatusDisposisiPenerima::Selesai && $this->disposisi->batas_waktu->isPast();
    }

    /** @return BelongsTo<Disposisi, $this> */
    public function disposisi(): BelongsTo
    {
        return $this->belongsTo(Disposisi::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /** @return HasMany<Disposisi, $this> */
    public function lanjutan(): HasMany
    {
        return $this->hasMany(Disposisi::class, 'induk_penerima_id');
    }
}
