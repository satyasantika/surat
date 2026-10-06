<?php

namespace App\Actions\Naskah;

use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\NaskahParaf;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Mengajukan draf untuk diparaf berurutan; tanpa pemaraf langsung menunggu tanda tangan. */
class AjukanParaf
{
    public function __construct(private readonly TransisiNaskah $transisi) {}

    /** @param  list<string>  $pemarafIds berurutan */
    public function jalankan(Naskah $naskah, User $pelaku, array $pemarafIds = []): Naskah
    {
        if ($naskah->penyusun_id !== $pelaku->getKey()) {
            throw new AuthorizationException('Hanya penyusun yang dapat mengajukan paraf.');
        }

        $pemarafIds = array_values(array_unique($pemarafIds));
        $pemaraf = $this->validasiPemaraf($pelaku, $pemarafIds);

        return $this->transisi->dalamKunci($naskah, function (Naskah $segar) use ($pelaku, $pemaraf) {
            $this->transisi->pastikanStatus($segar, [StatusNaskah::Draf], 'Hanya draf yang dapat diajukan.');

            // Putaran baru: paraf putaran sebelumnya (bila ada setelah dikembalikan) diganti; jejaknya ada di riwayat.
            $segar->paraf()->delete();

            foreach ($pemaraf as $i => $user) {
                NaskahParaf::create([
                    'naskah_id' => $segar->getKey(),
                    'user_id' => $user->getKey(),
                    'jabatan_id' => $user->jabatanAktif()->first()?->getKey(),
                    'urutan' => $i + 1,
                ]);
            }

            $this->transisi->ke(
                $segar,
                $pemaraf === [] ? StatusNaskah::MenungguTandaTangan : StatusNaskah::Paraf,
                $pelaku,
                $pemaraf === [] ? 'Diajukan tanpa paraf' : 'Diajukan untuk paraf',
            );

            return $segar;
        });
    }

    /**
     * @param  list<string>  $ids
     * @return list<User>
     */
    private function validasiPemaraf(User $penyusun, array $ids): array
    {
        $pemaraf = [];

        foreach ($ids as $id) {
            $user = User::where('aktif', true)->find($id);

            if (! $user || ! $user->can('naskah.paraf') || $user->is($penyusun)) {
                throw ValidationException::withMessages(['pemaraf' => 'Pemaraf harus pengguna aktif berizin paraf dan bukan penyusun.']);
            }

            $pemaraf[] = $user;
        }

        return $pemaraf;
    }
}
