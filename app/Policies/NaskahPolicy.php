<?php

namespace App\Policies;

use App\Models\Naskah;
use App\Models\User;

class NaskahPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->canAny(['naskah.draf', 'naskah.paraf', 'naskah.tandatangan', 'nomor.terbitkan']);
    }

    public function view(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->can('nomor.terbitkan')
            || $naskah->penyusun_id === $pelaku->getKey()
            || $this->pemangkuPenandaTangan($pelaku, $naskah);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('naskah.draf');
    }

    public function update(User $pelaku, Naskah $naskah): bool
    {
        return $naskah->penyusun_id === $pelaku->getKey() && $naskah->status->dapatDiubah();
    }

    public function delete(User $pelaku, Naskah $naskah): bool
    {
        return $naskah->penyusun_id === $pelaku->getKey() && $naskah->status->value === 'draf';
    }

    protected function pemangkuPenandaTangan(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->jabatanAktif()->contains('id', $naskah->penanda_tangan_jabatan_id);
    }
}
