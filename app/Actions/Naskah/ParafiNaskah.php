<?php

namespace App\Actions\Naskah;

use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\NaskahParaf;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Paraf oleh pemaraf yang gilirannya berjalan; paraf terakhir memajukan naskah ke tanda tangan. */
class ParafiNaskah
{
    public function __construct(private readonly TransisiNaskah $transisi) {}

    public function jalankan(Naskah $naskah, User $pelaku, ?string $catatan = null): Naskah
    {
        return $this->transisi->dalamKunci($naskah, function (Naskah $segar) use ($pelaku, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusNaskah::Paraf], 'Naskah tidak sedang menunggu paraf.');

            $giliran = $segar->parafBerjalan();

            if (! $giliran) {
                throw ValidationException::withMessages(['status' => 'Tidak ada paraf yang menunggu.']);
            }

            if ($giliran->user_id !== $pelaku->getKey()) {
                throw new AuthorizationException('Bukan giliran Anda untuk memaraf.');
            }

            $giliran->update(['status' => NaskahParaf::DISETUJUI, 'catatan' => $catatan, 'diputus_pada' => now()]);

            if ($segar->parafBerjalan() === null) {
                $this->transisi->ke($segar, StatusNaskah::MenungguTandaTangan, $pelaku, 'Paraf lengkap');
            } else {
                activity('naskah')->event('paraf')->performedOn($segar)->withProperties(['urutan' => $giliran->urutan])->log('Naskah diparaf');
            }

            return $segar;
        });
    }
}
