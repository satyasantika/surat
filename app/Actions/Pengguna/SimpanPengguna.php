<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Membuat/mengubah akun beserta peran dan izin langsung. Seluruh aturan otorisasi ditegakkan di sini
 * (bukan hanya di opsi formulir): peran terbatas hanya oleh super-admin, izin langsung hanya untuk
 * operator-layanan dan hanya izin yang dimiliki pelaku.
 */
class SimpanPengguna
{
    /** Peran yang hanya boleh diberikan oleh super-admin. */
    public const PERAN_TERBATAS = ['super-admin', 'admin-persuratan'];

    /**
     * @param  array<string, mixed>  $data  name, email, nip_nim, telepon, aktif, peran (list), izin_langsung (list)
     */
    public function jalankan(?User $user, array $data, User $pelaku): User
    {
        $data = $this->validasi($user, $data);
        $peran = array_values(array_unique($data['peran'] ?? []));
        $izin = array_values(array_unique($data['izin_langsung'] ?? []));

        $this->pastikanBolehMemberiPeran($pelaku, $peran, $user);
        $izin = in_array('operator-layanan', $peran, true) ? $izin : [];
        $this->pastikanBolehMemberiIzin($pelaku, $izin);

        if ($user && $user->is($pelaku) && ! ($data['aktif'] ?? true)) {
            throw ValidationException::withMessages(['aktif' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        return DB::transaction(function () use ($user, $data, $peran, $izin) {
            $profil = collect($data)->only(['name', 'email', 'nip_nim', 'telepon', 'aktif'])->all();
            $baru = $user === null;

            if ($baru) {
                // Kata sandi acak tak diketahui siapa pun; pengguna menyetelnya lewat surel.
                $user = User::create($profil + ['password' => Hash::make(Str::random(40))]);
            } else {
                $user->update($profil);
            }

            $peranLama = $user->roles()->pluck('name')->sort()->values()->all();
            $izinLama = $user->permissions()->pluck('name')->sort()->values()->all();

            $user->syncRoles($peran);
            $user->syncPermissions($izin);

            if ($peranLama !== collect($peran)->sort()->values()->all() || $izinLama !== collect($izin)->sort()->values()->all()) {
                activity('pengguna')->event('hak-akses')->performedOn($user)
                    ->withProperties(['peran_lama' => $peranLama, 'peran_baru' => $peran, 'izin_lama' => $izinLama, 'izin_baru' => $izin])
                    ->log('Peran/izin pengguna diubah');
            }

            if ($baru) {
                Password::sendResetLink(['email' => $user->email]);
            }

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validasi(?User $user, array $data): array
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id), new SurelDomainUnsil],
            'nip_nim' => ['nullable', 'string', 'max:30', Rule::unique('users', 'nip_nim')->ignore($user?->id)],
            'telepon' => ['nullable', 'string', 'max:20'],
            'aktif' => ['boolean'],
            'peran' => ['array'],
            'peran.*' => ['string', Rule::in(array_keys(PeranDanIzinSeeder::PERAN))],
            'izin_langsung' => ['array'],
            'izin_langsung.*' => ['string', Rule::in(PeranDanIzinSeeder::IZIN)],
        ])->validate();
    }

    /** @param  list<string>  $peran */
    protected function pastikanBolehMemberiPeran(User $pelaku, array $peran, ?User $user): void
    {
        if ($pelaku->hasRole('super-admin')) {
            return;
        }

        $terbatasBaru = array_intersect($peran, self::PERAN_TERBATAS);
        $terbatasLama = $user ? $user->roles->pluck('name')->intersect(self::PERAN_TERBATAS)->all() : [];

        // Tidak boleh menambah maupun mencabut peran terbatas.
        if (array_diff($terbatasBaru, $terbatasLama) !== [] || array_diff($terbatasLama, $terbatasBaru) !== []) {
            throw ValidationException::withMessages(['peran' => 'Hanya super-admin yang dapat mengatur peran super-admin atau admin-persuratan.']);
        }
    }

    /** @param  list<string>  $izin */
    protected function pastikanBolehMemberiIzin(User $pelaku, array $izin): void
    {
        foreach ($izin as $nama) {
            if (! $pelaku->can($nama)) {
                throw ValidationException::withMessages(['izin_langsung' => "Anda tidak dapat memberikan izin {$nama} karena tidak memilikinya."]);
            }
        }
    }
}
