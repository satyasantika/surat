<?php

namespace App\Policies;

use App\Models\Ormawa;
use App\Models\User;

/**
 * Admin dan peninjau (dekan, WD, kasubag) melihat semua; pembina atas binaannya; pengurus aktif atas
 * ormawanya; ketua/sekretaris aktif mengubah profil dan pengurus ormawanya sendiri.
 */
class OrmawaPolicy
{
    public function viewAny(User $p): bool
    {
        return $p->canAny(['ormawa.kelola', 'ormawa.lihat', 'ormawa.kelola-binaan']);
    }

    public function view(User $p, Ormawa $o): bool
    {
        return $p->canAny(['ormawa.kelola', 'ormawa.lihat'])
            || $o->pembina_user_id === $p->getKey()
            || $p->ormawaAktif()->contains('id', $o->getKey());
    }

    public function create(User $p): bool
    {
        return $p->can('ormawa.kelola');
    }

    public function update(User $p, Ormawa $o): bool
    {
        return $p->can('ormawa.kelola')
            || ($p->can('ormawa.kelola-binaan') && $o->pembina_user_id === $p->getKey())
            || $p->dapatMengelolaOrmawa($o);
    }

    public function delete(User $p, Ormawa $o): bool
    {
        return false;
    }
}
