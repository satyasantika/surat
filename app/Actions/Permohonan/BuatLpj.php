<?php

namespace App\Actions\Permohonan;

use App\Models\Lpj;
use App\Models\Permohonan;
use App\Support\Pengaturan;

/** LPJ draf dibuat otomatis saat permohonan selesai; batas waktu = tanggal selesai kegiatan + batas_hari_lpj (BR-16). */
class BuatLpj
{
    public function jalankan(Permohonan $permohonan): ?Lpj
    {
        $permohonan->loadMissing('jenis');

        if (! $permohonan->jenis->butuh_lpj) {
            return null;
        }

        $lpj = Lpj::where('permohonan_id', $permohonan->getKey())->first();

        if ($lpj === null) {
            $lpj = new Lpj;
            $lpj->permohonan_id = $permohonan->getKey();
            $lpj->batas_waktu = $permohonan->tanggal_selesai->addDays((int) Pengaturan::get('batas_hari_lpj'));
            $lpj->save();
        }

        return $lpj;
    }
}
