<?php

namespace App\Actions\Kabar;

use App\Models\Kabar;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Admin/operator menolak kabar diajukan dengan catatan wajib; penulis dapat memperbaiki dan mengajukan ulang. */
class TolakKabar
{
    public function jalankan(Kabar $kabar, User $pelaku, ?string $catatan): Kabar
    {
        $valid = Validator::make(['catatan' => $catatan], ['catatan' => ['required', 'string', 'max:2000']], ['catatan.required' => 'Catatan penolakan wajib diisi.'])->validate();

        return DB::transaction(function () use ($kabar, $pelaku, $valid) {
            $kabar = Kabar::lockForUpdate()->findOrFail($kabar->getKey());
            Gate::forUser($pelaku)->authorize('tolak', $kabar);

            $kabar->forceFill(['status' => Kabar::DITOLAK, 'catatan_admin' => $valid['catatan']])->save();
            app(NotifikasiAlur::class)->kabarDiputuskan($kabar, $pelaku);

            return $kabar;
        });
    }
}
