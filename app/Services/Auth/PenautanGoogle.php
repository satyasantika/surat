<?php

namespace App\Services\Auth;

use App\Models\IdentitasGoogle;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Menentukan pengguna untuk sebuah akun Google. Tidak pernah membuat atau menaikkan peran panel:
 * akun baru hanya mahasiswa (domain mahasiswa persis) atau pengirim-eksternal.
 */
class PenautanGoogle
{
    /**
     * @throws GagalMasukGoogle
     * @throws UniqueConstraintViolationException dua login pertama bersamaan untuk surel/akun Google yang sama
     */
    public function masuk(string $googleId, string $surel, bool $surelTerverifikasi, string $nama, ?string $hd = null): User
    {
        $surel = Str::lower(trim($surel));

        if (! $surelTerverifikasi) {
            throw new GagalMasukGoogle('Surel Google Anda belum terverifikasi.');
        }

        if (filled($hd) && Str::lower($hd) !== Str::after($surel, '@')) {
            throw new GagalMasukGoogle('Akun Google tidak sesuai dengan domain surelnya.');
        }

        return DB::transaction(function () use ($googleId, $surel, $nama): User {
            $identitas = IdentitasGoogle::where('google_id', $googleId)->first();
            $user = $identitas?->user;

            if (! $user) {
                $user = User::whereRaw('lower(email) = ?', [$surel])->first();

                if ($user) {
                    if ($user->identitasGoogle()->exists()) {
                        throw new GagalMasukGoogle('Surel ini sudah tertaut ke akun Google lain.');
                    }
                    $this->amankanAkunLama($user);
                } else {
                    $user = $this->buat($surel, $nama);
                }

                IdentitasGoogle::create(['user_id' => $user->id, 'google_id' => $googleId, 'surel' => $surel]);
            }

            if (! $user->aktif) {
                throw new GagalMasukGoogle('Surel atau kata sandi salah.');
            }

            return $user;
        });
    }

    /** Akun yang surelnya belum terbukti milik pemegangnya: kata sandi lama dibuang dan sesinya diakhiri. */
    private function amankanAkunLama(User $user): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill([
            'password' => Hash::make(Str::random(64)),
            'email_verified_at' => now(),
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function buat(string $surel, string $nama): User
    {
        $user = User::create([
            'name' => $nama !== '' ? $nama : Str::before($surel, '@'),
            'email' => $surel,
            'password' => Hash::make(Str::random(64)),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $mahasiswa = collect(config('unsil.domain_mahasiswa'))
            ->contains(fn (string $domain): bool => Str::endsWith($surel, '@'.$domain));

        $user->assignRole($mahasiswa ? 'mahasiswa' : 'pengirim-eksternal');

        return $user;
    }
}
