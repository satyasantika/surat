<?php

namespace App\Policies;

use App\Models\UnitKerja;
use App\Models\User;

class UnitKerjaPolicy extends MasterPolicy
{
    /** Mengajukan permohonan atas nama unit: akun berizin yang ditugaskan sebagai pengaju unit aktif. */
    public function ajukanPermohonan(User $pelaku, UnitKerja $unit): bool
    {
        return $unit->aktif && $pelaku->can('permohonan.ajukan-unit') && $pelaku->unitDiajukan()->whereKey($unit->getKey())->exists();
    }
}
