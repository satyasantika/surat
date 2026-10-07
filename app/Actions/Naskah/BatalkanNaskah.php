<?php

namespace App\Actions\Naskah;

use App\Actions\Nomor\TandaiNomorBatal;
use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\NomorTerpakai;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Membatalkan naskah bernomor: alasan wajib, nomor ditandai batal (tidak dipakai ulang), tetap di register. */
class BatalkanNaskah
{
    public function __construct(private readonly TransisiNaskah $transisi, private readonly TandaiNomorBatal $tandai) {}

    public function jalankan(Naskah $naskah, User $pelaku, string $alasan): Naskah
    {
        Gate::forUser($pelaku)->authorize('batalkan', $naskah);

        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 2000) {
            throw ValidationException::withMessages(['alasan' => 'Alasan pembatalan wajib diisi (5–2000 karakter).']);
        }

        return $this->transisi->dalamKunci($naskah, function (Naskah $segar) use ($pelaku, $alasan) {
            $this->transisi->pastikanStatus($segar, [StatusNaskah::Ditandatangani, StatusNaskah::Terbit], 'Hanya naskah ditandatangani/terbit yang dapat dibatalkan.');

            $segar->dibatalkan_pada = now();
            $segar->alasan_batal = $alasan;
            $this->transisi->ke($segar, StatusNaskah::Dibatalkan, $pelaku, $alasan);

            if ($segar->nomor_terpakai_id !== null) {
                $this->tandai->jalankan(NomorTerpakai::findOrFail($segar->nomor_terpakai_id), "Naskah dibatalkan: {$alasan}");
            }

            return $segar;
        });
    }
}
