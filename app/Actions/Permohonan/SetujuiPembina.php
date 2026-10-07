<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SetujuiPembina
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, ?string $catatan = null): Permohonan
    {
        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::PersetujuanPembina]);
            Gate::forUser($pelaku)->authorize('setujuiPembina', $segar);
            $this->transisi->ke($segar, StatusPermohonan::ValidasiAdmin, $pelaku, $catatan ?: 'Disetujui pembina');

            return $segar;
        });
    }
}
