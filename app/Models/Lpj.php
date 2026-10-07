<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property CarbonImmutable $batas_waktu
 * @property CarbonImmutable|null $tanggal_pelaksanaan
 */
#[Fillable(['tanggal_pelaksanaan', 'jumlah_peserta', 'ringkasan', 'kendala', 'solusi', 'rekomendasi', 'tautan_instagram', 'tautan_video'])]
class Lpj extends Model
{
    use HasUuids, TercatatAktivitas;

    public const DRAF = 'draf';

    public const DIAJUKAN = 'diajukan';

    public const DINILAI = 'dinilai';

    protected $table = 'lpj';

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return [
            'tanggal_pelaksanaan' => 'date',
            'batas_waktu' => 'date',
            'diajukan_pada' => 'datetime',
            'nilai_akhir' => 'decimal:2',
            'jumlah_peserta' => 'integer',
        ];
    }

    protected function namaLog(): string
    {
        return 'lpj';
    }

    /** Terlambat: belum diajukan dan melewati batas waktu ditambah toleransi (BR-16). */
    public function terlambat(): bool
    {
        return $this->status === self::DRAF
            && now()->startOfDay()->gt($this->batas_waktu->addDays((int) Pengaturan::get('toleransi_lpj_hari')));
    }

    /** @return BelongsTo<Permohonan, $this> */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
