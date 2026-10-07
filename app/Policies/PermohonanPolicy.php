<?php

namespace App\Policies;

use App\Models\Permohonan;
use App\Models\User;

/**
 * Pengurus aktif melihat permohonan ormawanya; pembina atas binaannya; admin, dekan, WD, kasubag melihat
 * semua untuk memutus. Data pribadi penanggung jawab (BR-18) hanya untuk admin validasi, ketua/sekretaris,
 * dan pengaju.
 */
class PermohonanPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->canAny(['permohonan.validasi', 'permohonan.putuskan', 'permohonan.setujui-pembina']);
    }

    public function view(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->canAny(['permohonan.validasi', 'permohonan.putuskan'])
            || $permohonan->ormawa->pembina_user_id === $pelaku->getKey()
            || $pelaku->ormawaAktif()->contains('id', $permohonan->ormawa_id);
    }

    public function lihatDataPribadi(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->can('permohonan.validasi')
            || $permohonan->diajukan_oleh === $pelaku->getKey()
            || $pelaku->dapatMengelolaOrmawa($permohonan->ormawa);
    }
}
