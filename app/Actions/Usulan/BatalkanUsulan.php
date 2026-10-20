<?php

namespace App\Actions\Usulan;

use App\Enums\StatusUsulan;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Mahasiswa membatalkan usulannya sendiri selama belum dipakai sebagai dasar permohonan. */
class BatalkanUsulan
{
    public function jalankan(UsulanMahasiswa $usulan, User $pelaku): UsulanMahasiswa
    {
        return DB::transaction(function () use ($usulan, $pelaku) {
            $segar = UsulanMahasiswa::whereKey($usulan->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($pelaku)->authorize('batalkan', $segar);

            if (! $segar->status->terbuka()) {
                throw ValidationException::withMessages(['status' => 'Usulan ini tidak dapat dibatalkan lagi.']);
            }

            $segar->status = StatusUsulan::Dibatalkan;
            $segar->save();

            return $segar;
        });
    }
}
