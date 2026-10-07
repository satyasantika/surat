<?php

namespace App\Policies;

use App\Models\Lpj;
use App\Models\User;

/**
 * Pengurus aktif ormawa mengisi LPJ ormawanya selama masih draf; pembina melihat binaannya; admin dan
 * pimpinan (penilai) melihat semuanya. Penilaian ditambahkan pada F8.2.
 */
class LpjPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->canAny(['permohonan.validasi', 'lpj.nilai', 'lpj.lihat']);
    }

    public function view(User $pelaku, Lpj $lpj): bool
    {
        $lpj->loadMissing('permohonan.ormawa');

        return $pelaku->canAny(['permohonan.validasi', 'lpj.nilai'])
            || $lpj->permohonan->ormawa->pembina_user_id === $pelaku->getKey()
            || $pelaku->ormawaAktif()->contains('id', $lpj->permohonan->ormawa_id);
    }

    public function isi(User $pelaku, Lpj $lpj): bool
    {
        $lpj->loadMissing('permohonan');

        return $lpj->status === Lpj::DRAF
            && $pelaku->can('lpj.isi')
            && $pelaku->ormawaAktif()->contains('id', $lpj->permohonan->ormawa_id);
    }
}
