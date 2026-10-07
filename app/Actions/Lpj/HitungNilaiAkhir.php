<?php

namespace App\Actions\Lpj;

use App\Models\Lpj;
use App\Models\RubrikLpj;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengaturan;

/**
 * Nilai akhir diisi hanya setelah SEMUA penilai pada SEMUA rubrik aktif mengisi (BR-17). Rumus dari
 * pengaturan rumus_nilai_lpj: jumlah (bawaan) atau persen (jumlah / total maksimum × 100).
 */
class HitungNilaiAkhir
{
    public function jalankan(Lpj $lpj): bool
    {
        $rubrik = RubrikLpj::where('aktif', true)->get();
        $dibutuhkan = $rubrik->sum(fn (RubrikLpj $r) => count($r->penilai_jabatan));

        if ($dibutuhkan === 0) {
            return false;
        }

        $nilai = $lpj->nilai()->whereIn('rubrik_lpj_id', $rubrik->pluck('id'))->get();

        if ($nilai->count() < $dibutuhkan) {
            return false;
        }

        $jumlah = (float) $nilai->sum('nilai');
        $akhir = Pengaturan::get('rumus_nilai_lpj') === 'persen' ? $jumlah / RubrikLpj::totalMaks() * 100 : $jumlah;

        $lpj->forceFill(['nilai_akhir' => round($akhir, 2), 'status' => Lpj::DINILAI])->save();
        app(NotifikasiAlur::class)->lpjDinilai($lpj);

        return true;
    }
}
