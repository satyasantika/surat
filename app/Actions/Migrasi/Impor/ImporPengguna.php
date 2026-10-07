<?php

namespace App\Actions\Migrasi\Impor;

use App\Models\ImporLog;
use App\Models\Jabatan;
use App\Models\PemangkuJabatan;
use App\Models\User;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\Pemetaan;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Users → users, peran, pemangku_jabatan (07-MIGRASI-DATA §4). Kata sandi dan urlTte tidak pernah dibaca;
 * akun diberi kata sandi acak yang tak diketahui siapa pun (pengguna mengatur sendiri lewat undangan).
 */
class ImporPengguna
{
    /** Tab admin_custom lama → permission baru (§4a). */
    private const IZIN_TAB = [
        'permohonan' => ['permohonan.validasi'],
        'penerbitan_surat' => ['naskah.draf', 'nomor.terbitkan'],
        'plot_ruangan' => ['ruangan.kelola-jadwal'],
        'admin_galeri' => ['kabar.kelola', 'galeri.kelola'],
        'settings' => [],
    ];

    private const JABATAN_BAWAAN = ['dekan' => 'dekan', 'kasubag' => 'kasubag-umum', 'kabag_umum' => 'kasubag-umum'];

    /** @param  list<array<string, mixed>>  $baris */
    public function jalankan(KonteksImpor $k, array $baris): void
    {
        foreach ($baris as $r) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            if (PolaContoh::barisContoh($r)) {
                $k->catat('Users', $id, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            $peranLama = strtolower(Sel::teks($r['role'] ?? null) ?? '');

            if ($peranLama === 'ormawa') {
                $k->catat('Users', $id, ImporLog::LEWATI, 'Akun ormawa bersama tidak dimigrasikan (S-12); pengurus membuat akun pribadi.');

                continue;
            }

            if ($baru = $k->sebelumnya('Users', $id)) {
                $k->catat('Users', $id, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', 'users', $baru);

                continue;
            }

            try {
                [$user, $peringatan] = DB::transaction(fn () => $this->buat($k, $r, $id, $peranLama));
                $k->catat('Users', $id, $peringatan === [] ? ImporLog::OK : ImporLog::PERINGATAN, $peringatan === [] ? null : implode(' ', $peringatan), 'users', $user->getKey());
            } catch (Throwable $e) {
                $k->catat('Users', $id, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{0: User, 1: list<string>}
     */
    private function buat(KonteksImpor $k, array $r, string $id, string $peranLama): array
    {
        $m = $k->pemetaan->pengguna($id) ?? throw new \RuntimeException('Tidak ada baris di pengguna.csv.');
        $peran = ($m['peran'] ?? '') !== '' ? $m['peran'] : (Pemetaan::PERAN[$peranLama] ?? throw new \RuntimeException("Peran lama '{$peranLama}' tidak dikenal."));
        $peringatan = [];

        $user = User::where('sumber_id_lama', $id)->first() ?? User::where('email', $m['email'])->first();

        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'name' => Str::limit(Sel::teks($r['name'] ?? null) ?? $m['email'], 150, ''),
                'email' => $m['email'],
                'password' => Hash::make(Str::random(64)),
                'telepon' => Sel::telepon($r['phone'] ?? null),
                'aktif' => true,
                'sumber_id_lama' => $id,
                'email_verified_at' => now(),
            ]);

            // department berisi NIP hanya untuk pejabat/admin (§4)
            $nip = preg_replace('/\s+/', '', (string) Sel::teks($r['department'] ?? null));

            if (preg_match('/^\d{8,30}$/', (string) $nip) === 1 && ! User::where('nip_nim', $nip)->exists()) {
                $user->nip_nim = $nip;
            }

            $user->save();
        } else {
            $user->sumber_id_lama ??= $id;
            $user->saveQuietly();
            $peringatan[] = 'Akun dengan surel ini sudah ada; ditautkan, kata sandi tidak diubah.';
        }

        $user->assignRole($peran);

        if ($peranLama === 'admin_custom') {
            $izin = [];

            foreach ($this->tab($r['permissions'] ?? null) as $tab) {
                if (! array_key_exists($tab, self::IZIN_TAB)) {
                    $peringatan[] = "Tab '{$tab}' tidak punya padanan permission; tetapkan manual.";

                    continue;
                }

                $izin = [...$izin, ...self::IZIN_TAB[$tab]];
            }

            $user->givePermissionTo(array_values(array_unique($izin)));
        }

        $kodeJabatan = ($m['jabatan'] ?? '') !== '' ? $m['jabatan']
            : (in_array($peranLama, ['wd1', 'wd2'], true) ? $k->pemetaan->jabatanWd($peranLama) : (self::JABATAN_BAWAAN[$peranLama] ?? null));

        if ($kodeJabatan !== null) {
            $jabatan = Jabatan::where('kode', $kodeJabatan)->first();

            if ($jabatan === null) {
                $peringatan[] = "Jabatan '{$kodeJabatan}' tidak ada; tetapkan manual.";
            } else {
                try {
                    DB::transaction(fn () => PemangkuJabatan::create(['jabatan_id' => $jabatan->getKey(), 'user_id' => $user->getKey(), 'mulai' => now()->toDateString(), 'plt' => false]));
                } catch (ValidationException) {
                    $peringatan[] = "Jabatan {$kodeJabatan} sudah memiliki pemangku definitif; tetapkan pemangku manual.";
                }
            }
        }

        if (Sel::teks($r['urlTte'] ?? null) !== null) {
            $peringatan[] = 'urlTte tidak dimigrasikan; pejabat mengunggah ulang tautan tanda tangan.';
        }

        return [$user, $peringatan];
    }

    /** @return list<string> */
    private function tab(mixed $nilai): array
    {
        $teks = Sel::teks($nilai);

        if ($teks === null) {
            return [];
        }

        $json = json_decode($teks, true);
        $daftar = is_array($json) ? $json : preg_split('/[,;|]/', $teks);

        return array_values(array_filter(array_map(fn ($t) => strtolower(trim((string) $t)), $daftar ?: []), fn ($t) => $t !== ''));
    }
}
