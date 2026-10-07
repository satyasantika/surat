<?php

namespace App\Actions\Ormawa;

use App\Models\Ormawa;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Ketua/sekretaris (atau admin/pembina) mengubah profil publik ormawa; nama, tingkat, pembina, status tidak termasuk. */
class SimpanProfilOrmawa
{
    /** @param  array<string, mixed>  $data */
    public function jalankan(Ormawa $ormawa, User $pelaku, array $data): Ormawa
    {
        Gate::forUser($pelaku)->authorize('update', $ormawa);

        $valid = Validator::make($data, [
            'singkatan' => ['nullable', 'string', 'max:30'],
            'akun_media' => ['nullable', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'surel_organisasi' => ['nullable', 'email', 'max:150'],
            'visi' => ['nullable', 'string', 'max:5000'],
            'misi' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $ormawa->update($valid);

        return $ormawa;
    }
}
