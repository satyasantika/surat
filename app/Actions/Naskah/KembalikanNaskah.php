<?php

namespace App\Actions\Naskah;

use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\NaskahParaf;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Mengembalikan naskah ke penyusun (catatan wajib) dari tahap paraf atau menunggu tanda tangan. */
class KembalikanNaskah
{
    public function __construct(private readonly TransisiNaskah $transisi) {}

    public function jalankan(Naskah $naskah, User $pelaku, string $catatan): Naskah
    {
        $catatan = trim($catatan);

        if ($catatan === '') {
            throw ValidationException::withMessages(['catatan' => 'Catatan pengembalian wajib diisi.']);
        }

        return $this->transisi->dalamKunci($naskah, function (Naskah $segar) use ($pelaku, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusNaskah::Paraf, StatusNaskah::MenungguTandaTangan]);

            if ($segar->status === StatusNaskah::Paraf) {
                $giliran = $segar->parafBerjalan();

                if (! $giliran || $giliran->user_id !== $pelaku->getKey()) {
                    throw new AuthorizationException('Hanya pemaraf yang sedang berjalan yang dapat mengembalikan.');
                }

                $giliran->update(['status' => NaskahParaf::DIKEMBALIKAN, 'catatan' => $catatan, 'diputus_pada' => now()]);
            } elseif (! $this->pemangkuPenandaTangan($pelaku, $segar)) {
                throw new AuthorizationException('Hanya penanda tangan yang dapat mengembalikan pada tahap ini.');
            }

            $this->transisi->ke($segar, StatusNaskah::Dikembalikan, $pelaku, $catatan);

            return $segar;
        });
    }

    private function pemangkuPenandaTangan(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->jabatanAktif()->contains('id', $naskah->penanda_tangan_jabatan_id);
    }
}
