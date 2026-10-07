<?php

namespace App\Actions\Migrasi;

use App\Models\User;
use App\Notifications\UndanganAturSandi;
use Illuminate\Support\Facades\Password;

/** Mengundang akun hasil migrasi mengatur kata sandi lewat antrean surel; setiap akun hanya diundang sekali. */
class UndangPengguna
{
    /**
     * @return array{diundang: int, dilewati: int}
     */
    public function jalankan(bool $dryRun = false, ?string $surel = null): array
    {
        $hasil = ['diundang' => 0, 'dilewati' => 0];

        User::whereNotNull('sumber_id_lama')->when($surel, fn ($q) => $q->where('email', $surel))->orderBy('created_at')->each(function (User $u) use (&$hasil, $dryRun) {
            if ($u->diundang_pada !== null || ! $u->aktif) {
                $hasil['dilewati']++;

                return;
            }

            $hasil['diundang']++;

            if ($dryRun) {
                return;
            }

            // Penanda ditulis lebih dulu: pemanggilan ulang (atau tabrakan dua proses) tidak mengirim dua kali.
            if (User::whereKey($u->getKey())->whereNull('diundang_pada')->update(['diundang_pada' => now()]) === 1) {
                $u->notify(new UndanganAturSandi(Password::broker()->createToken($u)));
            } else {
                $hasil['diundang']--;
                $hasil['dilewati']++;
            }
        });

        return $hasil;
    }
}
