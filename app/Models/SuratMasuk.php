<?php

namespace App\Models;

use App\Enums\DerajatKecepatan;
use App\Enums\JenisPengirim;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusSuratMasuk;
use App\Models\Concerns\MemilikiKodeLacak;
use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $tanggal_terima
 * @property CarbonImmutable $tanggal_surat
 * @property KlasifikasiKeamanan $klasifikasi_keamanan
 * @property DerajatKecepatan $derajat_kecepatan
 * @property StatusSuratMasuk $status
 * @property JenisPengirim $jenis_pengirim
 * @property ?CarbonImmutable $diteruskan_pada
 * @property ?string $pengirim_surel
 * @property ?string $pengirim_wa
 * @property string $kode_lacak
 * @property ?CarbonImmutable $hasil_dikirim_pada
 * @property ?string $hasil_dikirim_oleh
 */
#[Fillable(['nomor_agenda', 'tanggal_terima', 'nomor_surat', 'tanggal_surat', 'asal', 'perihal', 'ringkasan', 'lampiran', 'klasifikasi_arsip_id', 'klasifikasi_keamanan', 'derajat_kecepatan', 'unit_pengolah_id', 'status', 'diregistrasi_oleh', 'jenis_pengirim', 'pengirim_unit_kerja_id', 'pengirim_nama', 'pengirim_instansi', 'pengirim_surel', 'pengirim_wa'])]
class SuratMasuk extends Model
{
    use HasUuids, MemilikiKodeLacak, SoftDeletes, TercatatAktivitas;

    public const TOPENGAN = '[RAHASIA]';

    protected $table = 'surat_masuk';

    protected $attributes = ['klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'status' => 'diterima', 'jenis_pengirim' => 'eksternal'];

    /** Kontak pengirim (data pribadi) tidak ikut serialisasi maupun jejak audit. */
    protected $hidden = ['pengirim_surel', 'pengirim_wa', 'kode_lacak'];

    protected function casts(): array
    {
        return [
            'tanggal_terima' => 'datetime',
            'diarsipkan_pada' => 'datetime',
            'diteruskan_pada' => 'datetime',
            'hasil_dikirim_pada' => 'datetime',
            'jenis_pengirim' => JenisPengirim::class,
            'pengirim_surel' => 'encrypted',
            'pengirim_wa' => 'encrypted',
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

    /** @return BelongsTo<UnitKerja, $this> */
    public function pengirimUnitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'pengirim_unit_kerja_id');
    }

    /** @return BelongsTo<UnitPembuat, $this> */
    public function unitPembuat(): BelongsTo
    {
        return $this->belongsTo(UnitPembuat::class);
    }

    /** Tautan hasil cetak yang dikirim petugas unit kepada pengirim (BR-28), bila ada. */
    public function tautanHasil(): ?TautanBerkas
    {
        return $this->tautan()->where('jenis', TautanBerkas::JENIS_HASIL)->latest()->first();
    }

    /** @return BelongsTo<User, $this> */
    public function penerus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diteruskan_oleh');
    }

    /** Sudah diteruskan staf khusus ke dekan (atau jalur langsung tanpa staf khusus). */
    public function sudahDiteruskan(): bool
    {
        return $this->diteruskan_pada !== null;
    }

    /**
     * Dekan hanya melihat surat yang sudah diteruskan; pemegang izin registrasi/penerusan dan super-admin melihat semua.
     *
     * @param  Builder<SuratMasuk>  $query
     */
    public function scopeTampakBagi(Builder $query, ?User $pelaku): void
    {
        if ($pelaku !== null && $pelaku->hasRole('dekan') && ! $pelaku->hasRole('super-admin') && ! $pelaku->canAny(['masuk.registrasi', 'masuk.teruskan'])) {
            $query->whereNotNull('diteruskan_pada');
        }
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
