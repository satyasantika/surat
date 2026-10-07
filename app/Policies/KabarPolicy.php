<?php

namespace App\Policies;

use App\Models\Kabar;
use App\Models\User;

/** Admin/operator (kabar.kelola) mengelola semua; pengurus aktif mengusulkan dan mengubah usulannya selama draf/ditolak. */
class KabarPolicy
{
    public function viewAny(User $p): bool
    {
        return $p->can('kabar.kelola');
    }

    public function view(User $p, Kabar $k): bool
    {
        return $p->can('kabar.kelola') || $this->pengusul($p, $k);
    }

    public function create(User $p): bool
    {
        return $p->can('kabar.kelola') || $p->can('kabar.usul');
    }

    public function update(User $p, Kabar $k): bool
    {
        return $p->can('kabar.kelola')
            || ($this->pengusul($p, $k) && in_array($k->status, [Kabar::DRAF, Kabar::DITOLAK], true));
    }

    public function ajukan(User $p, Kabar $k): bool
    {
        return $this->update($p, $k) && in_array($k->status, [Kabar::DRAF, Kabar::DITOLAK], true);
    }

    public function terbitkan(User $p, Kabar $k): bool
    {
        return $p->can('kabar.kelola') && in_array($k->status, [Kabar::DRAF, Kabar::DIAJUKAN], true);
    }

    public function tolak(User $p, Kabar $k): bool
    {
        return $p->can('kabar.kelola') && $k->status === Kabar::DIAJUKAN;
    }

    public function delete(User $p, Kabar $k): bool
    {
        return $p->can('kabar.kelola');
    }

    private function pengusul(User $p, Kabar $k): bool
    {
        return $p->can('kabar.usul') && $k->penulis_id === $p->getKey() && $k->ormawa_id !== null
            && $p->ormawaAktif()->contains('id', $k->ormawa_id);
    }
}
