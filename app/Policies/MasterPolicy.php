<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Data master: dikelola pemegang izin master.kelola (super-admin lewat Gate::before). */
class MasterPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function view(User $pelaku, Model $record): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function update(User $pelaku, Model $record): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function delete(User $pelaku, Model $record): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function deleteAny(User $pelaku): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function import(User $pelaku): bool
    {
        return $pelaku->can('master.kelola');
    }
}
