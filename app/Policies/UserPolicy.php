<?php

namespace App\Policies;

use App\Actions\Pengguna\SimpanPengguna;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('pengguna.kelola');
    }

    public function view(User $pelaku, User $target): bool
    {
        return $this->update($pelaku, $target);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('pengguna.kelola');
    }

    public function update(User $pelaku, User $target): bool
    {
        return $pelaku->can('pengguna.kelola')
            && ($pelaku->hasRole('super-admin') || ! $target->hasAnyRole(SimpanPengguna::PERAN_TERBATAS));
    }

    /** Akun tidak dihapus (jejak audit); cukup dinonaktifkan. */
    public function delete(User $pelaku, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $pelaku): bool
    {
        return false;
    }
}
