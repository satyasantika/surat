<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

#[Fillable(['kode', 'nama', 'induk_id', 'retensi_aktif_tahun', 'retensi_inaktif_tahun', 'keterangan_akhir', 'aktif'])]
class KlasifikasiArsip extends Model
{
    use HasUuids, TercatatAktivitas;

    public const CACHE_KUNCI = 'surat:master:klasifikasi';

    public const KETERANGAN_AKHIR = ['musnah' => 'Musnah', 'permanen' => 'Permanen', 'dinilai_kembali' => 'Dinilai kembali'];

    protected $table = 'klasifikasi_arsip';

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'retensi_aktif_tahun' => 'integer', 'retensi_inaktif_tahun' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $k) {
            if ($k->induk_id && $k->exists && $k->memuat(self::findOrFail($k->induk_id))) {
                throw ValidationException::withMessages(['induk_id' => 'Induk tidak boleh diri sendiri atau turunannya.']);
            }
        });

        $lupakan = fn () => Cache::forget(self::CACHE_KUNCI);

        static::saved($lupakan);
        static::deleted($lupakan);
    }

    /**
     * Daftar klasifikasi aktif untuk pilihan (kode => "kode — nama"), ter-cache.
     *
     * @return array<string, string>
     */
    public static function untukPilihan(): array
    {
        return Cache::remember(self::CACHE_KUNCI, 3600, fn () => static::query()
            ->where('aktif', true)->orderBy('kode')->get()
            ->mapWithKeys(fn (self $k) => [$k->getKey() => "{$k->kode} — {$k->nama}"])->all());
    }

    /** @return BelongsTo<KlasifikasiArsip, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /** @return HasMany<KlasifikasiArsip, $this> */
    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    /** Apakah $kandidat adalah diri sendiri atau salah satu turunan (untuk mencegah siklus). */
    public function memuat(self $kandidat): bool
    {
        for ($node = $kandidat; $node !== null; $node = $node->induk) {
            if ($node->is($this)) {
                return true;
            }
        }

        return false;
    }
}
