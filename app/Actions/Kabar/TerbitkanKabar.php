<?php

namespace App\Actions\Kabar;

use App\Models\Kabar;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Admin/operator menyetujui kabar → terbit. */
class TerbitkanKabar
{
    public function jalankan(Kabar $kabar, User $pelaku): Kabar
    {
        return DB::transaction(function () use ($kabar, $pelaku) {
            $kabar = Kabar::lockForUpdate()->findOrFail($kabar->getKey());
            Gate::forUser($pelaku)->authorize('terbitkan', $kabar);

            $kabar->forceFill([
                'status' => Kabar::TERBIT, 'catatan_admin' => null,
                'disetujui_oleh' => $pelaku->getKey(), 'terbit_pada' => now(),
            ])->save();
            app(NotifikasiAlur::class)->kabarDiputuskan($kabar, $pelaku);

            return $kabar;
        });
    }
}
