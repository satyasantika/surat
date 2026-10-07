<?php

namespace App\Policies;

use App\Models\RuanganLokal;
use App\Models\User;

class RuanganLokalPolicy
{
    public function viewAny(User $p): bool
    {
        return $p->can('ruangan.kelola');
    }

    public function view(User $p, RuanganLokal $r): bool
    {
        return $p->can('ruangan.kelola');
    }

    public function create(User $p): bool
    {
        return $p->can('ruangan.kelola');
    }

    public function update(User $p, RuanganLokal $r): bool
    {
        return $p->can('ruangan.kelola');
    }

    public function delete(User $p, RuanganLokal $r): bool
    {
        return $p->can('ruangan.kelola') && ! $r->pemakaian()->exists();
    }
}
