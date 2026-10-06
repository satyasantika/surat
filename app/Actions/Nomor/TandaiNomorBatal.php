<?php

namespace App\Actions\Nomor;

use App\Models\NomorTerpakai;

class TandaiNomorBatal
{
    /** Menandai batal; urutnya tetap terpakai sehingga tidak diberikan ulang. */
    public function jalankan(NomorTerpakai $nomor, string $alasan): NomorTerpakai
    {
        if (! $nomor->dibatalkan) {
            $nomor->update(['dibatalkan' => true]);

            activity('nomor')->event('batal')->performedOn($nomor)
                ->withProperties(['alasan' => $alasan])->log("Nomor {$nomor->nomor_lengkap} dibatalkan");
        }

        return $nomor;
    }
}
