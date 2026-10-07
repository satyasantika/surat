<?php

namespace App\Support;

use App\Models\JenisNaskah;

/** Label mode tanda tangan; istilah "TTE" hanya untuk mode tersertifikasi (BR-09). */
class Mode
{
    public static function label(string $mode): string
    {
        return match ($mode) {
            'basah' => 'Tanda tangan basah',
            'visual' => 'Tanda tangan visual dengan kode QR verifikasi',
            'tte' => 'Tanda tangan elektronik tersertifikasi (TTE)',
            default => JenisNaskah::MODE_TANDA_TANGAN[$mode] ?? $mode,
        };
    }
}
