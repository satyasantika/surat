<?php

namespace App\Models;

use App\Enums\DerajatKecepatan;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusNaskah;
use App\Models\Concerns\TercatatAktivitas;
use App\Support\KonteksNaskah;
use App\Support\SanitasiHtml;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property StatusNaskah $status
 * @property KlasifikasiKeamanan $klasifikasi_keamanan
 * @property DerajatKecepatan $derajat_kecepatan
 * @property array<string, mixed> $data
 * @property array<string, mixed>|null $snapshot
 * @property CarbonImmutable|null $tanggal_naskah
 */
#[Fillable(['jenis_naskah_id', 'klasifikasi_arsip_id', 'klasifikasi_keamanan', 'derajat_kecepatan', 'perihal', 'data', 'isi', 'status', 'penyusun_id', 'penanda_tangan_jabatan_id', 'atas_nama', 'mode_tanda_tangan', 'permohonan_id'])]
class Naskah extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    /** Kolom yang tidak boleh berubah setelah terisi (BR-07, BR-08). */
    public const IMMUTABLE = ['nomor', 'tanggal_naskah', 'snapshot', 'hash_pdf', 'nomor_terpakai_id'];

    protected $table = 'naskah';

    protected $attributes = ['status' => 'draf', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'snapshot' => 'array',
            'status' => StatusNaskah::class,
            'klasifikasi_keamanan' => KlasifikasiKeamanan::class,
            'derajat_kecepatan' => DerajatKecepatan::class,
            'tanggal_naskah' => 'date',
            'ditandatangani_pada' => 'datetime',
            'diterbitkan_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $naskah) {
            // Isi kaya selalu disanitasi sebelum tersimpan.
            if ($naskah->isDirty('isi')) {
                $naskah->isi = SanitasiHtml::bersihkan($naskah->isi);
            }
        });

        static::updating(function (self $naskah) {
            foreach (self::IMMUTABLE as $kolom) {
                $asli = $naskah->getRawOriginal($kolom);

                if ($asli !== null && $naskah->isDirty($kolom)) {
                    throw new LogicException("Kolom {$kolom} tidak dapat diubah setelah terisi.");
                }
            }
        });

        // Hanya draf yang boleh dihapus (soft delete); selebihnya bagian dari register.
        static::deleting(function (self $naskah) {
            if (! $naskah->forceDeleting && $naskah->status !== StatusNaskah::Draf) {
                throw new LogicException('Hanya naskah berstatus draf yang dapat dihapus.');
            }
            if ($naskah->forceDeleting) {
                throw new LogicException('Naskah tidak dapat dihapus permanen.');
            }
        });
    }

    protected function namaLog(): string
    {
        return 'naskah';
    }

    /**
     * Konteks render: snapshot beku bila sudah ditandatangani, selain itu data hidup.
     *
     * @return array<string, mixed>
     */
    public function konteksRender(): array
    {
        return $this->snapshot ?? KonteksNaskah::dariDraf($this);
    }

    /**
     * Naskah yang boleh dilihat pengguna: seluruhnya bagi admin penomoran/super-admin, selain itu
     * yang disusun, ditandatangani (jabatan yang diemban), atau diparaf pengguna.
     *
     * @param  Builder<Naskah>  $query
     */
    public function scopeTerlihatOleh(Builder $query, User $pengguna): void
    {
        if ($pengguna->hasRole('super-admin') || $pengguna->can('nomor.terbitkan')) {
            return;
        }

        $jabatanIds = $pengguna->jabatanAktif()->pluck('id')->all();

        $query->where(fn (Builder $q) => $q
            ->where('penyusun_id', $pengguna->getKey())
            ->orWhereIn('penanda_tangan_jabatan_id', $jabatanIds)
            ->orWhereHas('paraf', fn (Builder $p) => $p->where('user_id', $pengguna->getKey())));
    }

    /** @return BelongsTo<JenisNaskah, $this> */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(JenisNaskah::class, 'jenis_naskah_id');
    }

    /** @return BelongsTo<KlasifikasiArsip, $this> */
    public function klasifikasiArsip(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiArsip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function penyusun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyusun_id');
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function penandaTanganJabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'penanda_tangan_jabatan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penandaTanganUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanda_tangan_user_id');
    }

    /** @return HasMany<NaskahTujuan, $this> */
    public function tujuan(): HasMany
    {
        return $this->hasMany(NaskahTujuan::class)->where('jenis', 'tujuan')->orderBy('urutan');
    }

    /** @return HasMany<NaskahTujuan, $this> */
    public function tembusan(): HasMany
    {
        return $this->hasMany(NaskahTujuan::class)->where('jenis', 'tembusan')->orderBy('urutan');
    }

    /** @return HasMany<NaskahTujuan, $this> */
    public function semuaTujuan(): HasMany
    {
        return $this->hasMany(NaskahTujuan::class)->orderBy('urutan');
    }

    /** @return HasMany<NaskahParaf, $this> */
    public function paraf(): HasMany
    {
        return $this->hasMany(NaskahParaf::class)->orderBy('urutan');
    }

    /** @return HasMany<RiwayatNaskah, $this> */
    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatNaskah::class)->orderBy('created_at')->orderBy('id');
    }

    /** Paraf yang sedang ditunggu (urutan terkecil yang belum diputus). */
    public function parafBerjalan(): ?NaskahParaf
    {
        return $this->paraf()->where('status', NaskahParaf::MENUNGGU)->first();
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
