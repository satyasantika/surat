<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Kasubag merekomendasikan penerbitan surat izin (atau menolak dengan alasan). */
class RekomendasiKasubag
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $kasubag, ?string $catatan = null): Permohonan
    {
        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($kasubag, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::RekomendasiKasubag]);
            Gate::forUser($kasubag)->authorize('rekomendasiKasubag', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Penerbitan, $kasubag, $catatan ?: 'Direkomendasikan kasubag');

            return $segar;
        });
    }

    public function tolak(Permohonan $permohonan, User $kasubag, string $alasan): Permohonan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($kasubag, $alasan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::RekomendasiKasubag]);
            Gate::forUser($kasubag)->authorize('rekomendasiKasubag', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Ditolak, $kasubag, $alasan);

            return $segar;
        });
    }
}
