<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** @property array<int, string> $penilai_jabatan kode jabatan penilai */
#[Fillable(['kode', 'nama', 'nilai_maks', 'penilai_jabatan', 'urutan', 'aktif'])]
class RubrikLpj extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'rubrik_lpj';

    protected $attributes = ['aktif' => true, 'urutan' => 0];

    protected function casts(): array
    {
        return ['penilai_jabatan' => 'array', 'aktif' => 'boolean', 'nilai_maks' => 'integer'];
    }

    protected function namaLog(): string
    {
        return 'rubrik-lpj';
    }

    protected static function booted(): void
    {
        static::saving(function (self $r) {
            $kode = array_values(array_unique($r->penilai_jabatan ?? []));

            if ($kode === [] || Jabatan::whereIn('kode', $kode)->count() !== count($kode)) {
                throw ValidationException::withMessages(['penilai_jabatan' => 'Penilai harus berupa satu atau lebih kode jabatan yang ada.']);
            }

            if ($r->nilai_maks < 1 || $r->nilai_maks > 100) {
                throw ValidationException::withMessages(['nilai_maks' => 'Nilai maksimum harus 1–100.']);
            }
        });
    }

    /** Total nilai maksimum seluruh rubrik aktif (satu nilai per aspek per penilai). */
    public static function totalMaks(): int
    {
        return (int) self::where('aktif', true)->get()->sum(fn (self $r) => $r->nilai_maks * count($r->penilai_jabatan));
    }
}
