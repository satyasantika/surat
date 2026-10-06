<?php

namespace App\Services\TandaTangan;

use App\Contracts\PenandaTangan;
use App\Models\Naskah;
use App\Models\User;
use App\Services\Naskah\PengambilGambarTtd;

/** Gambar tanda tangan pejabat (tautan ttd_visual, diambil server saja) + QR verifikasi. Bukan "TTE". */
class Visual implements PenandaTangan
{
    public function __construct(private readonly ?PengambilGambarTtd $pengambil = null) {}

    public function mode(): string
    {
        return 'visual';
    }

    public function siapkan(Naskah $naskah, User $penandatangan): array
    {
        // Gambar dibekukan ke snapshot saat tanda tangan agar render ulang deterministik.
        return ['mode' => 'visual', 'ttd_gambar' => ($this->pengambil ?? new PengambilGambarTtd)->dataUri($penandatangan)];
    }
}
