<?php

namespace App\Models;

use App\Enums\JenisPengaju;
use App\Enums\StatusPermohonan;
use App\Models\Concerns\MemilikiKodeLacak;
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
 * @property JenisPengaju $jenis_pengaju
 * @property ?string $unit_kerja_id
 * @property ?string $ormawa_id
 * @property string|null $mode_wd
 * @property string $kode_lacak
 * @property ?CarbonImmutable $hasil_dikirim_pada
 * @property ?string $hasil_dikirim_oleh
 * @property CarbonImmutable $tanggal_mulai
 * @property CarbonImmutable $tanggal_selesai
 * @property CarbonImmutable $diajukan_pada
 * @property array<string, array<string, mixed>> $penanggung_jawab
 * @property list<array<string, mixed>>|null $fasilitas_rektorat
 */
#[Fillable(['ormawa_id', 'jenis_pengaju', 'unit_kerja_id', 'jenis_permohonan_id', 'diajukan_oleh', 'nama_kegiatan', 'perihal', 'nomor_surat_ormawa', 'tanggal_mulai', 'tanggal_selesai', 'jam_mulai', 'jam_selesai', 'tempat_lain', 'deskripsi', 'penanggung_jawab', 'fasilitas_rektorat', 'alasan_mendesak'])]
class Permohonan extends Model
{
    use HasUuids, MemilikiKodeLacak, TercatatAktivitas;

    public const MODE_WD_SEMUA = 'semua';

    public const MODE_WD_SALAH_SATU = 'salah_satu';

    /** @var array<string, string> */
    public const MODE_WD = [
        self::MODE_WD_SALAH_SATU => 'Cukup salah satu WD',
        self::MODE_WD_SEMUA => 'Semua WD tujuan harus setuju',
    ];

    protected $table = 'permohonan';

    protected $attributes = ['sumber' => 'aplikasi', 'jenis_pengaju' => 'ormawa'];

    /** Data pribadi penanggung jawab (BR-18) tidak ikut serialisasi maupun jejak audit. */
    protected $hidden = ['penanggung_jawab', 'kode_lacak', 'diajukan_atas_nama'];

    protected function casts(): array
    {
        return [
            'status' => StatusPermohonan::class,
            'jenis_pengaju' => JenisPengaju::class,
            'diajukan_atas_nama' => 'encrypted',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'diajukan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'hasil_dikirim_pada' => 'datetime',
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

    /**
     * Unit kerja pengaju (prodi/laboratorium); kosong untuk pengaju ormawa.
     *
     * @return BelongsTo<UnitKerja, $this>
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /** Nama pihak pengaju untuk tampilan: ormawa atau unit kerja. */
    public function namaPengaju(): string
    {
        return $this->jenis_pengaju->berunit() ? (string) $this->unitKerja?->nama : (string) $this->ormawa?->nama;
    }

    /** Penerima surat izin: ketua ormawa, ketua program studi, atau kepala laboratorium. */
    public function sebutanPimpinanPengaju(): string
    {
        return match ($this->jenis_pengaju) {
            JenisPengaju::Ormawa => 'Ketua '.$this->namaPengaju(),
            JenisPengaju::Prodi => 'Ketua Program Studi '.$this->namaPengaju(),
            JenisPengaju::Laboratorium => 'Kepala '.$this->namaPengaju(),
        };
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

    /** Mode persetujuan WD: `semua` (bawaan, BR-14) atau `salah_satu` (BR-25). */
    public function modeWd(): string
    {
        return $this->mode_wd ?? self::MODE_WD_SEMUA;
    }

    /** @return BelongsTo<Naskah, $this> */
    public function naskahIzin(): BelongsTo
    {
        return $this->belongsTo(Naskah::class, 'naskah_izin_id');
    }

    /**
     * Usulan mahasiswa yang menjadi dasar permohonan ini (satu atau beberapa digabung oleh perantara).
     *
     * @return HasMany<UsulanMahasiswa, $this>
     */
    public function usulanDasar(): HasMany
    {
        return $this->hasMany(UsulanMahasiswa::class);
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
