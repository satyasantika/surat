<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Impor massal pengguna dari teks salin-tempel Excel/Google Sheets (pemegang izin pengguna.kelola).
 *
 * Kolom berurutan: nama, surel[, peran[, NIP/NIM[, telepon]]]. Beberapa peran dipisah "|". Pemisah kolom otomatis
 * (tab, titik koma, atau koma); baris judul (kolom surel tanpa "@") dilewati.
 *
 * Idempoten: surel yang sudah terdaftar dilewati (tidak diubah). Semua-atau-tidak-sama-sekali: satu baris salah
 * membatalkan seluruh impor. Setiap akun disimpan lewat SimpanPengguna sehingga aturan domain, keunikan, dan
 * pembatasan peran terbatas sama dengan formulir satuan. `periksa()` dipakai untuk pratinjau tanpa menulis apa pun.
 */
class ImporPenggunaTempel
{
    public const int MAKS_BARIS = 500;

    public function __construct(private SimpanPengguna $simpan) {}

    /**
     * @return array{baru: list<array{baris: int, name: string, email: string, peran: list<string>, nip_nim: ?string, telepon: ?string}>, dilewati: list<string>, galat: list<string>}
     */
    public function periksa(string $teks, User $pelaku): array
    {
        [$baris, $galat] = $this->urai($teks);

        if ($baris === [] && $galat === []) {
            return ['baru' => [], 'dilewati' => [], 'galat' => ['Tidak ada baris data untuk diimpor.']];
        }

        if (count($baris) > self::MAKS_BARIS) {
            return ['baru' => [], 'dilewati' => [], 'galat' => ['Maksimal '.self::MAKS_BARIS.' baris per impor.']];
        }

        $surelAda = User::query()->pluck('email')->map(fn (string $e): string => Str::lower($e))->flip();
        $nimAda = User::query()->whereNotNull('nip_nim')->pluck('nip_nim')->flip();
        $superAdmin = $pelaku->hasRole('super-admin');
        $baru = [];
        $dilewati = [];
        $surelTerlihat = [];
        $nimTerlihat = [];

        foreach ($baris as $nomor => [$nama, $surel, $peranTeks, $nim, $telepon]) {
            $surel = Str::lower($surel);

            // Akun yang sudah ada tidak diubah, sehingga isi kolom lainnya (dan domain surel lama) tidak divalidasi.
            if ($surel !== '' && $surelAda->has($surel) && ! isset($surelTerlihat[$surel])) {
                $surelTerlihat[$surel] = $nomor;
                $dilewati[] = $surel;

                continue;
            }

            $pesan = Validator::make(
                ['nama' => $nama, 'surel' => $surel, 'NIP/NIM' => $nim, 'telepon' => $telepon],
                [
                    'nama' => ['required', 'string', 'max:150'],
                    'surel' => ['required', 'email', 'max:255', new SurelDomainUnsil],
                    'NIP/NIM' => ['nullable', 'string', 'max:30'],
                    'telepon' => ['nullable', 'string', 'max:20'],
                ],
            )->errors()->all();

            if ($surel !== '' && isset($surelTerlihat[$surel])) {
                $pesan[] = "surel {$surel} ganda dalam data tempel (baris {$surelTerlihat[$surel]})";
            }
            $surelTerlihat[$surel] ??= $nomor;

            $peran = [];

            foreach (array_filter(array_map('trim', explode('|', $peranTeks))) as $p) {
                $kunci = $this->peran($p);

                if ($kunci === null) {
                    $pesan[] = "peran '{$p}' tidak dikenal";
                } elseif (! $superAdmin && in_array($kunci, SimpanPengguna::PERAN_TERBATAS, true)) {
                    $pesan[] = "hanya super-admin yang dapat memberi peran {$kunci}";
                } else {
                    $peran[] = $kunci;
                }
            }

            if ($nim !== '') {
                if ($nimAda->has($nim)) {
                    $pesan[] = "NIP/NIM {$nim} sudah dipakai akun lain";
                } elseif (isset($nimTerlihat[$nim])) {
                    $pesan[] = "NIP/NIM {$nim} ganda dalam data tempel (baris {$nimTerlihat[$nim]})";
                }
                $nimTerlihat[$nim] ??= $nomor;
            }

            if ($pesan !== []) {
                $galat[] = "Baris {$nomor}: ".implode('; ', array_map(fn (string $m): string => rtrim($m, '.'), $pesan)).'.';

                continue;
            }

            $baru[] = [
                'baris' => $nomor, 'name' => $nama, 'email' => $surel, 'peran' => array_values(array_unique($peran)),
                'nip_nim' => $nim !== '' ? $nim : null, 'telepon' => $telepon !== '' ? $telepon : null,
            ];
        }

        // Galat urai (jumlah kolom) dan galat validasi digabung menurut nomor baris.
        usort($galat, fn (string $a, string $b): int => (int) preg_replace('/\D.*/s', '', substr($a, 6)) <=> (int) preg_replace('/\D.*/s', '', substr($b, 6)));

        return ['baru' => $galat === [] ? $baru : [], 'dilewati' => $dilewati, 'galat' => $galat];
    }

    /**
     * Menyimpan akun baru dalam satu transaksi. Bila $kirimTautan, tautan atur kata sandi dikirim lewat antrean
     * sesudah transaksi berhasil (tidak ada surel untuk impor yang dibatalkan).
     *
     * @return array{dibuat: int, dilewati: list<string>, galat: list<string>}
     */
    public function handle(string $teks, User $pelaku, bool $kirimTautan = true): array
    {
        if (! $pelaku->can('pengguna.kelola')) {
            throw new AuthorizationException('Anda tidak berwenang mengimpor pengguna.');
        }

        $hasil = $this->periksa($teks, $pelaku);

        if ($hasil['galat'] !== []) {
            return ['dibuat' => 0, 'dilewati' => $hasil['dilewati'], 'galat' => $hasil['galat']];
        }

        try {
            $dibuat = DB::transaction(function () use ($hasil, $pelaku, $kirimTautan): array {
                $dibuat = [];

                foreach ($hasil['baru'] as $data) {
                    try {
                        $akun = $this->simpan->jalankan(null, [
                            'name' => $data['name'], 'email' => $data['email'], 'nip_nim' => $data['nip_nim'],
                            'telepon' => $data['telepon'], 'aktif' => true, 'peran' => $data['peran'], 'izin_langsung' => [],
                        ], $pelaku, kirimTautan: false);
                    } catch (ValidationException $e) {
                        // Bentrok yang muncul setelah pratinjau (mis. akun dibuat bersamaan): batalkan seluruh impor.
                        throw ValidationException::withMessages(['teks' => "Baris {$data['baris']}: ".collect($e->errors())->flatten()->implode(' ')]);
                    }

                    if ($kirimTautan) {
                        $surel = $akun->email;
                        dispatch(fn () => Password::sendResetLink(['email' => $surel]))->afterCommit();
                    }

                    $dibuat[] = $akun->email;
                }

                activity('pengguna')->causedBy($pelaku)->event('impor-tempel')
                    ->withProperties(['dibuat' => $dibuat, 'dilewati' => $hasil['dilewati'], 'tautan_sandi' => $kirimTautan])
                    ->log('Impor tempel pengguna: '.count($dibuat).' dibuat, '.count($hasil['dilewati']).' dilewati');

                return $dibuat;
            });
        } catch (ValidationException $e) {
            return ['dibuat' => 0, 'dilewati' => $hasil['dilewati'], 'galat' => collect($e->errors())->flatten()->values()->all()];
        }

        return ['dibuat' => count($dibuat), 'dilewati' => $hasil['dilewati'], 'galat' => []];
    }

    /**
     * @return array{0: array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>, 1: list<string>}
     */
    private function urai(string $teks): array
    {
        $hasil = [];
        $galat = [];

        foreach (preg_split('/\R/u', trim($teks)) ?: [] as $i => $baris) {
            $nomor = $i + 1;

            if (trim($baris) === '') {
                continue;
            }

            $pemisah = str_contains($baris, "\t") ? "\t" : (str_contains($baris, ';') ? ';' : ',');
            $kolom = array_map(fn (?string $k): string => trim((string) $k), str_getcsv($baris, $pemisah, '"', ''));

            // Baris judul (kolom surel tanpa @) di baris pertama data dilewati.
            if ($hasil === [] && $galat === [] && ! str_contains($kolom[1] ?? '', '@')) {
                continue;
            }

            if (count($kolom) < 2) {
                $galat[] = "Baris {$nomor}: minimal berisi nama dan surel.";

                continue;
            }

            $hasil[$nomor] = [$kolom[0], $kolom[1], $kolom[2] ?? '', $kolom[3] ?? '', $kolom[4] ?? ''];
        }

        return [$hasil, $galat];
    }

    /** Peran dikenali dari kuncinya (mis. "pengurus-ormawa"), tanpa membedakan huruf besar/kecil; spasi = tanda hubung. */
    private function peran(string $teks): ?string
    {
        $kunci = Str::of($teks)->lower()->trim()->replaceMatches('/\s+/', '-')->toString();

        return array_key_exists($kunci, PeranDanIzinSeeder::PERAN) ? $kunci : null;
    }
}
