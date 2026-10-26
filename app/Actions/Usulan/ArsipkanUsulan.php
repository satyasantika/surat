<?php

namespace App\Actions\Usulan;

use App\Enums\StatusUsulan;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Perantara mengarsipkan kiriman pihak luar (mis. undangan) tanpa meneruskannya ke fakultas. Status akhir, tanpa
 * tindak lanjut; tidak menyentuh kiriman lain dari pengiriman yang sama (mis. surat masuk ke fakultas) dan tidak
 * mengisi permohonan_id. Usulan mahasiswa tidak dapat diarsipkan: dikembalikan, ditolak, atau dijadikan dasar.
 */
class ArsipkanUsulan
{
    public function jalankan(UsulanMahasiswa $usulan, User $pelaku): UsulanMahasiswa
    {
        return DB::transaction(function () use ($usulan, $pelaku) {
            $segar = UsulanMahasiswa::whereKey($usulan->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($pelaku)->authorize('tangani', $segar);

            if ($segar->status !== StatusUsulan::Diajukan) {
                throw ValidationException::withMessages(['status' => 'Usulan ini sudah diputus atau dipakai.']);
            }

            if (! $segar->dariPihakLuar()) {
                throw ValidationException::withMessages(['status' => 'Hanya kiriman pihak luar yang dapat diarsipkan. Usulan mahasiswa dikembalikan, ditolak, atau dijadikan dasar permohonan.']);
            }

            $segar->status = StatusUsulan::Diarsipkan;
            $segar->save();
            app(NotifikasiAlur::class)->usulanDiarsipkan($segar, $pelaku);

            return $segar;
        });
    }
}
