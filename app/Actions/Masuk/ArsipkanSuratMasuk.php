<?php

namespace App\Actions\Masuk;

use App\Enums\StatusSuratMasuk;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Admin mengarsipkan surat masuk yang telah selesai (RS-09); sejak itu surat dihitung sebagai arsip untuk retensi. */
class ArsipkanSuratMasuk
{
    public function jalankan(SuratMasuk $surat, User $pelaku): SuratMasuk
    {
        return DB::transaction(function () use ($surat, $pelaku) {
            $surat = SuratMasuk::lockForUpdate()->findOrFail($surat->getKey());
            Gate::forUser($pelaku)->authorize('arsipkan', $surat);

            if ($surat->status !== StatusSuratMasuk::Selesai) {
                throw ValidationException::withMessages(['status' => 'Hanya surat berstatus selesai yang dapat diarsipkan.']);
            }

            $surat->forceFill(['status' => StatusSuratMasuk::Diarsipkan, 'diarsipkan_pada' => now(), 'diarsipkan_oleh' => $pelaku->getKey()])->save();
            activity('surat-masuk')->event('arsipkan')->performedOn($surat)->log('Surat masuk diarsipkan');

            return $surat;
        });
    }
}
