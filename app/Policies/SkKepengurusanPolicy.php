<?php

namespace App\Policies;

use App\Models\SkKepengurusan;
use App\Models\User;

/** SK mengikuti hak atas ormawanya (dipakai juga oleh TautanBerkasPolicy untuk membuka dokumen SK). */
class SkKepengurusanPolicy
{
    public function view(User $pelaku, SkKepengurusan $sk): bool
    {
        return $pelaku->can('view', $sk->ormawa);
    }

    public function update(User $pelaku, SkKepengurusan $sk): bool
    {
        return $pelaku->can('update', $sk->ormawa);
    }
}
