<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UsulanMahasiswa;

/**
 * Pengusul (mahasiswa, atau pengirim pihak luar untuk kiriman ke ormawa/prodi/lab) melihat dan membatalkan usulannya
 * sendiri. Perantara (pengurus ormawa atau pengaju unit) menangani usulan yang ditujukan kepada ormawa/unitnya:
 * mengembalikan, menolak, menjadikan dasar, atau (khusus kiriman pihak luar) mengarsipkan.
 */
class UsulanMahasiswaPolicy
{
    public function view(User $pelaku, UsulanMahasiswa $usulan): bool
    {
        return $usulan->mahasiswa_id === $pelaku->getKey() || $this->tangani($pelaku, $usulan);
    }

    public function batalkan(User $pelaku, UsulanMahasiswa $usulan): bool
    {
        return $usulan->mahasiswa_id === $pelaku->getKey() && $usulan->status->terbuka();
    }

    /** Pengurus aktif ormawa tujuan, atau pengaju yang ditugaskan pada unit tujuan, memilah usulan. */
    public function tangani(User $pelaku, UsulanMahasiswa $usulan): bool
    {
        if ($usulan->ormawa_id !== null) {
            return $pelaku->can('permohonan.ajukan') && $pelaku->ormawaAktif()->contains('id', $usulan->ormawa_id);
        }

        return $usulan->unit_kerja_id !== null && $pelaku->can('permohonan.ajukan-unit')
            && $pelaku->unitDiajukan()->whereKey($usulan->unit_kerja_id)->exists();
    }
}
