<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Admin memvalidasi kelengkapan; permohonan maju ke disposisi dekan. */
class ValidasiPermohonan
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, ?string $catatan = null): Permohonan
    {
        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::ValidasiAdmin]);
            Gate::forUser($pelaku)->authorize('validasi', $segar);
            $this->transisi->ke($segar, StatusPermohonan::DisposisiDekan, $pelaku, $catatan ?: 'Divalidasi admin');

            return $segar;
        });
    }
}
