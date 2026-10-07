<?php

namespace App\Actions\Kabar;

use App\Models\Kabar;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Draf atau kabar ditolak → diajukan ke admin. */
class AjukanKabar
{
    public function jalankan(Kabar $kabar, User $pelaku): Kabar
    {
        return DB::transaction(function () use ($kabar, $pelaku) {
            $kabar = Kabar::lockForUpdate()->findOrFail($kabar->getKey());
            Gate::forUser($pelaku)->authorize('ajukan', $kabar);

            $kabar->forceFill(['status' => Kabar::DIAJUKAN, 'catatan_admin' => null])->save();
            app(NotifikasiAlur::class)->kabarDiajukan($kabar, $pelaku);

            return $kabar;
        });
    }
}
