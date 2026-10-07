<?php

namespace App\Services\Notifikasi;

use App\Models\Jabatan;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Collection;

/** Penentu penerima notifikasi: pemangku jabatan, pemegang izin, dan pengurus ormawa aktif. */
class Penerima
{
    /** @return Collection<int, User> pemangku jabatan (definitif dan Plt) hari ini */
    public static function jabatan(string $kode): Collection
    {
        $jabatan = Jabatan::firstWhere('kode', $kode);

        if ($jabatan === null) {
            return collect();
        }

        return PemangkuJabatan::where('jabatan_id', $jabatan->getKey())->berlakuPada(now())->with('user')->get()->pluck('user')->filter()->values();
    }

    /** @return Collection<int, User> */
    public static function izin(string $izin): Collection
    {
        return User::permission($izin)->where('aktif', true)->get();
    }

    /** @return Collection<int, User> akun pengurus aktif ormawa + pengaju (bila diberikan) */
    public static function ormawa(Ormawa $ormawa, ?Permohonan $permohonan = null): Collection
    {
        $pengurus = PengurusOrmawa::where('ormawa_id', $ormawa->getKey())->whereNotNull('user_id')->with(['user', 'ormawa', 'sk'])->get()
            ->filter(fn (PengurusOrmawa $p) => $p->aktifPada())->pluck('user');

        $permohonan?->loadMissing('pengaju');

        return $pengurus->push($permohonan?->pengaju)->filter()->unique('id')->values();
    }
}
