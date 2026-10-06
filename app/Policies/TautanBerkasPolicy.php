<?php

namespace App\Policies;

use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Akses tautan mengikuti izin pada pemiliknya (surat, permohonan, dst.). Fail-closed: pemilik tanpa
 * policy tidak dapat dibuka (kecuali super-admin lewat Gate::before), dan jenis tertutup tidak pernah.
 */
class TautanBerkasPolicy
{
    public function view(User $pelaku, TautanBerkas $tautan): bool
    {
        if ($tautan->tertutup()) {
            return false;
        }

        $pemilik = $tautan->pemilik;

        if ($pemilik === null || Gate::getPolicyFor($pemilik) === null) {
            return false;
        }

        return $pelaku->can('view', $pemilik);
    }
}
