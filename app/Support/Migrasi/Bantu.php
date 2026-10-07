<?php

namespace App\Support\Migrasi;

use App\Models\Jabatan;
use App\Models\PemangkuJabatan;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;

/** Fungsi bantu bersama pengimpor OrmawaHub. */
class Bantu
{
    /** URL berkas yang lolos kebijakan (https, daftar putih, bukan pemendek/contoh); selain itu null. */
    public static function urlBerkas(?string $url): ?string
    {
        if ($url === null || PolaContoh::kolomContoh($url) || Validator::make(['u' => $url], ['u' => [new TautanBerkasValid(false)]])->fails()) {
            return null;
        }

        return $url;
    }

    /** Pemangku jabatan pada tanggal; bila data lama mendahului masa jabatan tercatat, pemangku terbaru. */
    public static function pemangku(string $kodeJabatan, ?CarbonInterface $tanggal = null): ?User
    {
        $jabatan = Jabatan::firstWhere('kode', $kodeJabatan);

        if ($jabatan === null) {
            return null;
        }

        $p = $tanggal !== null ? $jabatan->pemangkuPada($tanggal) : null;
        $p ??= PemangkuJabatan::where('jabatan_id', $jabatan->getKey())->latest('mulai')->first();

        return $p?->user;
    }

    /**
     * Mengisi $k->peta['ormawa_nama'] (nama lama atau baru, huruf kecil → id ormawa baru) dari sheet profil.
     *
     * @param  list<array<string, mixed>>  $profil
     */
    public static function petaNamaOrmawa(KonteksImpor $k, array $profil): void
    {
        foreach ($profil as $r) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            $baru = $k->peta['Ormawa_Profiles'][$id] ?? $k->sebelumnya('Ormawa_Profiles', $id);

            if ($baru === null) {
                continue;
            }

            $nama = [Sel::teks($r['nama'] ?? null), $k->pemetaan->ormawa($id)['nama'] ?? null];

            foreach ($nama as $n) {
                if ($n !== null && $n !== '') {
                    $k->peta['ormawa_nama'][mb_strtolower($n)] = $baru;
                }
            }
        }
    }

    public static function ormawaDariNama(KonteksImpor $k, ?string $nama): ?string
    {
        return $nama === null ? null : ($k->peta['ormawa_nama'][mb_strtolower(trim($nama))] ?? null);
    }
}
