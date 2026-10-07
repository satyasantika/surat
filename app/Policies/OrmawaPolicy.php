<?php

namespace App\Policies;

use App\Models\Ormawa;
use App\Models\User;

class OrmawaPolicy
{
    public function viewAny(User $p): bool
    {
        return $p->canAny(['ormawa.kelola', 'ormawa.lihat', 'ormawa.kelola-binaan']);
    }

    public function view(User $p, Ormawa $o): bool
    {
        return $p->canAny(['ormawa.kelola', 'ormawa.lihat']) || $o->pembina_user_id === $p->getKey();
    }

    public function create(User $p): bool
    {
        return $p->can('ormawa.kelola');
    }

    public function update(User $p, Ormawa $o): bool
    {
        return $p->can('ormawa.kelola') || ($p->can('ormawa.kelola-binaan') && $o->pembina_user_id === $p->getKey());
    }

    public function delete(User $p, Ormawa $o): bool
    {
        return false;
    }
}
