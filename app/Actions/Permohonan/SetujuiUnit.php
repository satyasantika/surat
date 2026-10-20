<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Kepala program studi/laboratorium menyetujui permohonan unitnya; berikutnya validasi admin fakultas. */
class SetujuiUnit
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, ?string $catatan = null): Permohonan
    {
        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::PersetujuanUnit]);
            Gate::forUser($pelaku)->authorize('setujuiUnit', $segar);
            $this->transisi->ke($segar, StatusPermohonan::ValidasiAdmin, $pelaku, $catatan ?: 'Disetujui kepala unit');

            return $segar;
        });
    }
}
