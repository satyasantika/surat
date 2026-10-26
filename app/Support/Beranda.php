<?php

namespace App\Support;

use App\Models\User;

/** Halaman beranda sesuai peran pengguna di luar panel admin. */
class Beranda
{
    public static function url(User $pengguna): string
    {
        return match (true) {
            $pengguna->hasAnyRole(['dekan', 'wakil-dekan', 'kasubag', 'pegawai']) => route('disposisi'),
            $pengguna->hasRole('pengurus-ormawa') => route('ormawa'),
            $pengguna->hasAnyRole(['pengaju-unit', 'kaprodi', 'kalab']) => route('unit'),
            $pengguna->hasRole('mahasiswa') => route('mahasiswa'),
            $pengguna->hasRole('pengirim-eksternal') => route('pengirim'),
            default => route('profil'),
        };
    }
}
