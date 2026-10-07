<?php

namespace App\Policies;

use App\Models\ImporLog;
use App\Models\User;

/** Log migrasi berisi id dan pesan data lama: hanya super-admin (lewat Gate::before); selain itu ditolak. */
class ImporLogPolicy
{
    public function viewAny(User $p): bool
    {
        return false;
    }

    public function view(User $p, ImporLog $l): bool
    {
        return false;
    }
}
