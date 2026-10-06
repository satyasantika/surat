<?php

namespace App\Services\TandaTangan;

use App\Contracts\PenandaTangan;
use App\Models\Naskah;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Gambar tanda tangan pejabat (tautan ttd_visual, dibaca server saja) + QR verifikasi. Bukan "TTE". */
class Visual implements PenandaTangan
{
    public function mode(): string
    {
        return 'visual';
    }

    public function siapkan(Naskah $naskah, User $penandatangan): array
    {
        $ada = TautanBerkas::where('pemilik_type', $penandatangan->getMorphClass())
            ->where('pemilik_id', $penandatangan->getKey())
            ->where('jenis', 'ttd_visual')
            ->exists();

        if (! $ada) {
            throw ValidationException::withMessages(['mode_tanda_tangan' => 'Penanda tangan belum memiliki gambar tanda tangan visual (tautan ttd_visual).']);
        }

        return ['mode' => 'visual'];
    }
}
