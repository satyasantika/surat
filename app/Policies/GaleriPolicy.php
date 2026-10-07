<?php

namespace App\Policies;

use App\Models\Galeri;
use App\Models\User;

/** Galeri dikelola pemegang izin galeri.kelola (admin/operator dan super-admin lewat Gate::before). */
class GaleriPolicy
{
    public function viewAny(User $p): bool
    {
        return $p->can('galeri.kelola');
    }

    public function view(User $p, Galeri $g): bool
    {
        return $p->can('galeri.kelola');
    }

    public function create(User $p): bool
    {
        return $p->can('galeri.kelola');
    }

    public function update(User $p, Galeri $g): bool
    {
        return $p->can('galeri.kelola');
    }

    public function delete(User $p, Galeri $g): bool
    {
        return $p->can('galeri.kelola');
    }

    public function deleteAny(User $p): bool
    {
        return $p->can('galeri.kelola');
    }
}
