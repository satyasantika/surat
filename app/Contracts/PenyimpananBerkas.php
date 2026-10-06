<?php

namespace App\Contracts;

use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Seluruh akses berkas lewat antarmuka ini agar kelak dapat dialihkan ke disk/S3 (STANDAR-TEKNIS §1a.7). */
interface PenyimpananBerkas
{
    public function simpan(Model $pemilik, string $jenis, string $url, ?string $label, ?User $oleh = null): TautanBerkas;

    /** @return Collection<int, TautanBerkas> */
    public function daftar(Model $pemilik, ?string $jenis = null): Collection;

    /** URL tujuan untuk pengguna berwenang; null bila jenis tertutup. */
    public function urlAkses(TautanBerkas $tautan): ?string;

    public function hapus(TautanBerkas $tautan): void;
}
