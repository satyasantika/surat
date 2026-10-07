<?php

namespace App\Models;

use App\Enums\StatusPermohonan;
use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property StatusPermohonan $status
 * @property CarbonImmutable $tanggal_mulai
 * @property CarbonImmutable $tanggal_selesai
 * @property CarbonImmutable $diajukan_pada
 * @property array<string, array<string, mixed>> $penanggung_jawab
 * @property list<array<string, mixed>>|null $fasilitas_rektorat
 */
#[Fillable(['ormawa_id', 'jenis_permohonan_id', 'diajukan_oleh', 'nama_kegiatan', 'perihal', 'nomor_surat_ormawa', 'tanggal_mulai', 'tanggal_selesai', 'jam_mulai', 'jam_selesai', 'tempat_lain', 'deskripsi', 'penanggung_jawab', 'fasilitas_rektorat', 'alasan_mendesak'])]
class Permohonan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'permohonan';

    protected $attributes = ['sumber' => 'aplikasi'];

    /** Data pribadi penanggung jawab (BR-18) tidak ikut serialisasi maupun jejak audit. */
    protected $hidden = ['penanggung_jawab'];

    protected function casts(): array
    {
        return [
            'status' => StatusPermohonan::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'diajukan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'penanggung_jawab' => 'encrypted:array',
            'fasilitas_rektorat' => 'array',
        ];
    }

    protected function namaLog(): string
    {
        return 'permohonan';
    }

    /** @return BelongsTo<Ormawa, $this> */
    public function ormawa(): BelongsTo
    {
        return $this->belongsTo(Ormawa::class);
    }

    /** @return BelongsTo<JenisPermohonan, $this> */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisPermohonan::class, 'jenis_permohonan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<Naskah, $this> */
    public function naskahIzin(): BelongsTo
    {
        return $this->belongsTo(Naskah::class, 'naskah_izin_id');
    }

    /** @return HasMany<PermohonanRuangan, $this> */
    public function ruangan(): HasMany
    {
        return $this->hasMany(PermohonanRuangan::class)->orderBy('tanggal')->orderBy('sesi');
    }

    /** @return HasMany<PersetujuanWd, $this> */
    public function persetujuanWd(): HasMany
    {
        return $this->hasMany(PersetujuanWd::class);
    }

    /** @return HasMany<Disposisi, $this> */
    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class);
    }

    /** @return HasMany<RiwayatPermohonan, $this> */
    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPermohonan::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
