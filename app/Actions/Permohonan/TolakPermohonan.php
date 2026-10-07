<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Menolak permohonan pada tahap pembina/validasi (alasan wajib); ruangan yang ditahan dilepas. */
class TolakPermohonan
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, string $alasan): Permohonan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $alasan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::PersetujuanPembina, StatusPermohonan::ValidasiAdmin]);
            Gate::forUser($pelaku)->authorize('putusTahapAwal', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Ditolak, $pelaku, $alasan);

            return $segar;
        });
    }
}
