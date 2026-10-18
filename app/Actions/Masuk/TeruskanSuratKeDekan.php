<?php

namespace App\Actions\Masuk;

use App\Enums\StatusSuratMasuk;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Staf khusus meneruskan surat masuk yang sudah diregistrasi ke dekan (BR-24); sejak itu dekan melihatnya. */
class TeruskanSuratKeDekan
{
    public function jalankan(SuratMasuk $surat, User $pelaku, ?string $catatan = null): SuratMasuk
    {
        if ($catatan !== null && mb_strlen($catatan) > 2000) {
            throw ValidationException::withMessages(['catatan' => 'Catatan penerusan paling banyak 2000 karakter.']);
        }

        return DB::transaction(function () use ($surat, $pelaku, $catatan) {
            $surat = SuratMasuk::lockForUpdate()->findOrFail($surat->getKey());
            Gate::forUser($pelaku)->authorize('teruskan', $surat);

            if ($surat->status !== StatusSuratMasuk::Diterima || $surat->sudahDiteruskan()) {
                throw ValidationException::withMessages(['status' => 'Surat ini sudah diteruskan ke dekan.']);
            }

            $surat->forceFill(['diteruskan_pada' => now(), 'diteruskan_oleh' => $pelaku->getKey()])->save();

            activity('surat-masuk')->event('teruskan')->performedOn($surat)->causedBy($pelaku)
                ->withProperties(['ke' => 'dekan', 'catatan' => $catatan])
                ->log('Surat masuk diteruskan ke dekan');

            app(NotifikasiAlur::class)->suratDiteruskan($surat, $pelaku, $catatan);

            return $surat;
        });
    }
}
