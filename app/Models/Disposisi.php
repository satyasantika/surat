<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $batas_waktu
 * @property array<int, string> $instruksi
 */
#[Fillable(['surat_masuk_id', 'permohonan_id', 'induk_penerima_id', 'nomor', 'dari_user_id', 'dari_jabatan_id', 'instruksi', 'catatan', 'batas_waktu', 'sifat'])]
class Disposisi extends Model
{
    use HasUuids, TercatatAktivitas;

    /** Pilihan instruksi baku (03-SKEMA §4). */
    public const INSTRUKSI = [
        'tindak_lanjuti' => 'Tindak lanjuti',
        'pelajari' => 'Pelajari',
        'hadiri' => 'Hadiri',
        'siapkan_jawaban' => 'Siapkan jawaban',
        'arsipkan' => 'Arsipkan',
    ];

    protected $table = 'disposisi';

    protected function casts(): array
    {
        return ['instruksi' => 'array', 'batas_waktu' => 'datetime'];
    }

    /** @return BelongsTo<SuratMasuk, $this> */
    public function suratMasuk(): BelongsTo
    {
        return $this->belongsTo(SuratMasuk::class);
    }

    /** @return BelongsTo<User, $this> */
    public function dari(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dari_user_id');
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function dariJabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'dari_jabatan_id');
    }

    /** @return BelongsTo<DisposisiPenerima, $this> */
    public function indukPenerima(): BelongsTo
    {
        return $this->belongsTo(DisposisiPenerima::class, 'induk_penerima_id');
    }

    /** @return HasMany<DisposisiPenerima, $this> */
    public function penerima(): HasMany
    {
        return $this->hasMany(DisposisiPenerima::class);
    }
}
