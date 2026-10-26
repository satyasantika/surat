<?php

namespace App\Support;

use App\Enums\TipeUnitKerja;
use App\Models\Ormawa;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Tujuan perantara usulan, ditulis "ormawa:{uuid}" atau "unit:{uuid}". Dipakai bersama usulan mahasiswa
 * (AjukanUsulan) dan kiriman pihak luar (AjukanKirimanEksternal). Ormawa harus aktif; unit harus program studi atau
 * laboratorium aktif yang memiliki pengaju aktif (pengelola usulan).
 */
class TujuanUsulan
{
    public const POLA = '/^(ormawa|unit):[0-9a-f-]{36}$/';

    /** @throws ValidationException bila tujuan tidak ditemukan, tidak aktif, atau tanpa pengelola */
    public static function urai(string $tujuan, string $kunci = 'tujuan'): Ormawa|UnitKerja
    {
        if (preg_match(self::POLA, $tujuan) !== 1) {
            throw ValidationException::withMessages([$kunci => 'Tujuan usulan tidak valid.']);
        }

        [$jenis, $id] = explode(':', $tujuan, 2);

        if ($jenis === 'ormawa') {
            return Ormawa::where('aktif', true)->find($id)
                ?? throw ValidationException::withMessages([$kunci => 'Ormawa tujuan tidak ditemukan atau tidak aktif.']);
        }

        $unit = self::unit()->find($id)
            ?? throw ValidationException::withMessages([$kunci => 'Program studi atau laboratorium tujuan tidak ditemukan atau tidak aktif.']);

        if (! $unit->pengaju()->where('users.aktif', true)->exists()) {
            throw ValidationException::withMessages([$kunci => 'Unit tujuan belum memiliki pengelola usulan. Hubungi admin fakultas.']);
        }

        return $unit;
    }

    /** @return Builder<UnitKerja> program studi/laboratorium aktif */
    public static function unit(): Builder
    {
        return UnitKerja::where('aktif', true)->whereIn('tipe', [TipeUnitKerja::Prodi->value, TipeUnitKerja::Laboratorium->value]);
    }

    /** @return Builder<UnitKerja> program studi/laboratorium aktif yang dapat dipilih (punya pengaju aktif) */
    public static function unitDapatDipilih(): Builder
    {
        return self::unit()->whereHas('pengaju', fn (Builder $q) => $q->where('users.aktif', true));
    }
}
