<?php

namespace App\Actions\Usulan;

use App\Enums\StatusUsulan;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Perantara mengembalikan (untuk diperbaiki) atau menolak usulan mahasiswa; catatan wajib dan dibaca mahasiswa. */
class PutusUsulan
{
    public function kembalikan(UsulanMahasiswa $usulan, User $pelaku, string $catatan): UsulanMahasiswa
    {
        return $this->putus($usulan, $pelaku, StatusUsulan::Dikembalikan, $catatan);
    }

    public function tolak(UsulanMahasiswa $usulan, User $pelaku, string $catatan): UsulanMahasiswa
    {
        return $this->putus($usulan, $pelaku, StatusUsulan::Ditolak, $catatan);
    }

    private function putus(UsulanMahasiswa $usulan, User $pelaku, StatusUsulan $ke, string $catatan): UsulanMahasiswa
    {
        $catatan = trim($catatan);

        if ($catatan === '') {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi.']);
        }

        return DB::transaction(function () use ($usulan, $pelaku, $ke, $catatan) {
            $segar = UsulanMahasiswa::whereKey($usulan->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($pelaku)->authorize('tangani', $segar);

            if ($segar->status !== StatusUsulan::Diajukan) {
                throw ValidationException::withMessages(['status' => 'Usulan ini sudah diputus atau dipakai.']);
            }

            $segar->forceFill(['status' => $ke, 'catatan' => $catatan])->save();
            app(NotifikasiAlur::class)->usulanDiputus($segar, $pelaku);

            return $segar;
        });
    }
}
