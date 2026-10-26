<?php

namespace App\Actions\Kiriman;

use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Pengirim eksternal menarik kirimannya selama belum diterima fakultas (hapus lunak); setelah diterima tidak dapat diubah. */
class TarikKirimanEksternal
{
    public function jalankan(SuratMasuk $surat, User $pelaku): void
    {
        DB::transaction(function () use ($surat, $pelaku) {
            $surat = SuratMasuk::lockForUpdate()->findOrFail($surat->getKey());

            if ($surat->pengirim_user_id === null || $surat->pengirim_user_id !== $pelaku->getKey()) {
                throw new AuthorizationException('Anda tidak berhak menarik kiriman ini.');
            }

            if (! $surat->masihDiajukan()) {
                throw ValidationException::withMessages(['status' => 'Kiriman sudah diterima fakultas dan tidak dapat ditarik.']);
            }

            Gate::forUser($pelaku)->authorize('tarik', $surat);

            // Jejak audit `deleted` dicatat otomatis oleh model (tanpa kontak pengirim).
            $surat->delete();
        });
    }
}
