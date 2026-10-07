<?php

namespace App\Actions\Ormawa;

use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menautkan akun pribadi ke data pengurus dengan mencocokkan NIM (BR-01, S-12) dan memberi peran
 * pengurus-ormawa. Tidak otomatis saat pendaftaran: harus oleh admin atau ketua/sekretaris ormawa itu,
 * dan hanya untuk akun aktif dengan surel terverifikasi.
 */
class TautkanAkunPengurus
{
    public function jalankan(PengurusOrmawa $pengurus, User $pelaku): PengurusOrmawa
    {
        Gate::forUser($pelaku)->authorize('update', $pengurus);

        if ($pengurus->user_id !== null) {
            throw ValidationException::withMessages(['user_id' => 'Pengurus ini sudah tertaut ke sebuah akun.']);
        }

        if (blank($pengurus->nim)) {
            throw ValidationException::withMessages(['nim' => 'NIM pengurus belum diisi.']);
        }

        $akun = User::where('nip_nim', $pengurus->nim)->where('aktif', true)->whereNotNull('email_verified_at')->first();

        if ($akun === null) {
            throw ValidationException::withMessages(['nim' => 'Tidak ada akun aktif bersurel terverifikasi dengan NIM tersebut.']);
        }

        if (PengurusOrmawa::where('ormawa_id', $pengurus->ormawa_id)->where('user_id', $akun->getKey())->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Akun tersebut sudah tertaut ke pengurus lain di ormawa ini.']);
        }

        return DB::transaction(function () use ($pengurus, $akun, $pelaku) {
            $pengurus->update(['user_id' => $akun->getKey()]);

            if (! $akun->hasRole('pengurus-ormawa')) {
                $akun->assignRole('pengurus-ormawa');
            }

            activity('pengurus-ormawa')->event('tautkan')->performedOn($pengurus)
                ->withProperties(['akun' => $akun->getKey(), 'oleh' => $pelaku->getKey()])->log('Akun ditautkan ke pengurus');

            return $pengurus;
        });
    }

    /** Menautkan semua pengurus ormawa yang NIM-nya cocok; mengembalikan jumlah yang berhasil. */
    public function semuaDiOrmawa(Ormawa $ormawa, User $pelaku): int
    {
        $berhasil = 0;

        foreach ($ormawa->pengurus()->with('ormawa')->whereNull('user_id')->whereNotNull('nim')->get() as $pengurus) {
            try {
                $this->jalankan($pengurus, $pelaku);
                $berhasil++;
            } catch (ValidationException) {
                // lewati yang belum punya akun cocok
            }
        }

        return $berhasil;
    }
}
