<?php

namespace App\Models;

use App\Enums\DerajatKecepatan;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusSuratMasuk;
use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $tanggal_terima
 * @property KlasifikasiKeamanan $klasifikasi_keamanan
 * @property DerajatKecepatan $derajat_kecepatan
 * @property StatusSuratMasuk $status
 */
#[Fillable(['nomor_agenda', 'tanggal_terima', 'nomor_surat', 'tanggal_surat', 'asal', 'perihal', 'ringkasan', 'lampiran', 'klasifikasi_arsip_id', 'klasifikasi_keamanan', 'derajat_kecepatan', 'unit_pengolah_id', 'status', 'diregistrasi_oleh'])]
class SuratMasuk extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    public const TOPENGAN = '[RAHASIA]';

    protected $table = 'surat_masuk';

    protected $attributes = ['klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'status' => 'diterima'];

    protected function casts(): array
    {
        return [
            'tanggal_terima' => 'datetime',
            'tanggal_surat' => 'date',
            'klasifikasi_keamanan' => KlasifikasiKeamanan::class,
            'derajat_kecepatan' => DerajatKecepatan::class,
            'status' => StatusSuratMasuk::class,
        ];
    }

    protected function namaLog(): string
    {
        return 'surat-masuk';
    }

    /** Perihal sesuai hak akses pelaku: disamarkan bila rahasia dan pelaku tidak berhak melihat isi. */
    public function perihalUntuk(?User $pelaku): string
    {
        return $this->klasifikasi_keamanan->tertutup() && ! $pelaku?->can('view', $this)
            ? self::TOPENGAN
            : $this->perihal;
    }

    /** @return BelongsTo<KlasifikasiArsip, $this> */
    public function klasifikasiArsip(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiArsip::class);
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function unitPengolah(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_pengolah_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diregistrasi_oleh');
    }

    /** @return HasMany<Disposisi, $this> */
    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class);
    }

    /** @return HasManyThrough<DisposisiPenerima, Disposisi, $this> */
    public function penerimaDisposisi(): HasManyThrough
    {
        return $this->hasManyThrough(DisposisiPenerima::class, Disposisi::class);
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
