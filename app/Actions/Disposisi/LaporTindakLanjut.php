<?php

namespace App\Actions\Disposisi;

use App\Enums\StatusDisposisiPenerima;
use App\Models\DisposisiPenerima;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class LaporTindakLanjut
{
    public function jalankan(DisposisiPenerima $penerima, User $pelaku, string $laporan): DisposisiPenerima
    {
        if ($penerima->user_id !== $pelaku->getKey()) {
            throw new AuthorizationException('Hanya penerima yang dapat melapor.');
        }

        if ($penerima->status === StatusDisposisiPenerima::Selesai) {
            throw ValidationException::withMessages(['laporan' => 'Disposisi sudah selesai.']);
        }

        $laporan = trim($laporan);

        if ($laporan === '' || mb_strlen($laporan) > 5000) {
            throw ValidationException::withMessages(['laporan' => 'Laporan tindak lanjut wajib diisi (maks. 5000 karakter).']);
        }

        $penerima->update([
            'status' => StatusDisposisiPenerima::Ditindaklanjuti,
            'laporan_tindak_lanjut' => $laporan,
            'dibaca_pada' => $penerima->dibaca_pada ?? now(),
            'ditindaklanjuti_pada' => now(),
        ]);

        return $penerima;
    }
}
