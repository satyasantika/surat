<?php

namespace App\Policies;

use App\Models\PengurusOrmawa;
use App\Models\User;

/**
 * Pengurus mengikuti hak atas ormawanya. Data pribadi (NIM, telepon; BR-18) hanya untuk admin, pembina
 * binaan, ketua/sekretaris ormawa tersebut, dan orangnya sendiri.
 */
class PengurusOrmawaPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->canAny(['ormawa.kelola', 'ormawa.lihat', 'ormawa.kelola-binaan']);
    }

    public function view(User $pelaku, PengurusOrmawa $pengurus): bool
    {
        return $pelaku->can('view', $pengurus->ormawa);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->canAny(['ormawa.kelola', 'ormawa.kelola-binaan', 'ormawa.kelola-sendiri']);
    }

    public function update(User $pelaku, PengurusOrmawa $pengurus): bool
    {
        return $pelaku->can('update', $pengurus->ormawa);
    }

    public function delete(User $pelaku, PengurusOrmawa $pengurus): bool
    {
        return $this->update($pelaku, $pengurus);
    }

    public function lihatDataPribadi(User $pelaku, PengurusOrmawa $pengurus): bool
    {
        return $pengurus->user_id === $pelaku->getKey() || $pelaku->can('update', $pengurus->ormawa);
    }
}
