<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Ormawa membatalkan permohonannya sebelum penerbitan; ruangan ditahan dilepas. */
class BatalkanPermohonan
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, string $alasan): Permohonan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan pembatalan wajib diisi.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $alasan) {
            $segar->loadMissing('ormawa');
            Gate::forUser($pelaku)->authorize('batalkan', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Dibatalkan, $pelaku, $alasan);

            return $segar;
        });
    }
}
