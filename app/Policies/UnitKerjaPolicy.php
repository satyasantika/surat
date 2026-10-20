<?php

namespace App\Policies;

use App\Models\UnitKerja;
use App\Models\User;

class UnitKerjaPolicy extends MasterPolicy
{
    /** Melihat daftar dan linimasa permohonan unit: pengaju atau kepala unit yang ditugaskan (unit aktif). */
    public function lihatPermohonan(User $pelaku, UnitKerja $unit): bool
    {
        return $unit->aktif && (
            ($pelaku->can('permohonan.ajukan-unit') && $pelaku->unitDiajukan()->whereKey($unit->getKey())->exists())
            || ($pelaku->can('permohonan.setujui-unit') && $pelaku->unitDisetujui()->whereKey($unit->getKey())->exists())
        );
    }

    /** Mengajukan permohonan atas nama unit: akun berizin yang ditugaskan sebagai pengaju unit aktif. */
    public function ajukanPermohonan(User $pelaku, UnitKerja $unit): bool
    {
        return $unit->aktif && $pelaku->can('permohonan.ajukan-unit') && $pelaku->unitDiajukan()->whereKey($unit->getKey())->exists();
    }
}
