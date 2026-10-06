<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** "Masuk sebagai" (BR-22): hanya super-admin, tidak bertingkat, tercatat di log aktivitas. */
class MulaiImpersonasi
{
    public function jalankan(User $admin, User $target): void
    {
        abort_unless(Gate::forUser($admin)->allows('impersonasi'), 403);
        abort_if(session()->has('impersonator_id'), 403, 'Sedang dalam mode masuk sebagai.');
        abort_if($target->is($admin) || ! $target->aktif || $target->hasRole('super-admin'), 403);

        activity('impersonasi')->event('mulai')->performedOn($target)->log("Masuk sebagai {$target->email}");

        session()->regenerate();
        Auth::login($target);
        session()->forget('password_hash_web');
        session()->put('impersonator_id', $admin->getKey());
    }
}
