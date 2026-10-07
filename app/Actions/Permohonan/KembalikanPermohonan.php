<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Mengembalikan ke ormawa untuk direvisi (catatan wajib); ruangan tetap ditahan. */
class KembalikanPermohonan
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, string $catatan): Permohonan
    {
        $catatan = trim($catatan);

        if ($catatan === '') {
            throw ValidationException::withMessages(['catatan' => 'Catatan pengembalian wajib diisi.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::PersetujuanPembina, StatusPermohonan::ValidasiAdmin]);
            Gate::forUser($pelaku)->authorize('putusTahapAwal', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Dikembalikan, $pelaku, $catatan);

            return $segar;
        });
    }
}
