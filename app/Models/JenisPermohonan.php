<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** @property array<int, string> $berkas_wajib */
#[Fillable(['kode', 'nama', 'butuh_ruangan', 'butuh_fasilitas_rektorat', 'jenis_naskah_id', 'berkas_wajib', 'aktif'])]
class JenisPermohonan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'jenis_permohonan';

    protected $attributes = ['aktif' => true, 'butuh_ruangan' => false, 'butuh_fasilitas_rektorat' => false];

    protected function casts(): array
    {
        return ['butuh_ruangan' => 'boolean', 'butuh_fasilitas_rektorat' => 'boolean', 'berkas_wajib' => 'array', 'aktif' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $jenis) {
            $tak = array_diff($jenis->berkas_wajib ?? [], config('berkas.jenis'));

            if ($tak !== []) {
                throw ValidationException::withMessages(['berkas_wajib' => 'Jenis berkas tidak dikenal: '.implode(', ', $tak)]);
            }
        });
    }

    /** @return BelongsTo<JenisNaskah, $this> */
    public function jenisNaskah(): BelongsTo
    {
        return $this->belongsTo(JenisNaskah::class);
    }
}
