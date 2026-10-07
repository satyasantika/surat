<?php

namespace App\Support\Migrasi;

use App\Models\Jabatan;
use App\Models\Ormawa;
use App\Models\Role;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Tabel pemetaan CSV (07-MIGRASI-DATA §3): pengguna.csv, ormawa.csv, ruangan.csv, wd.csv. Divalidasi lengkap
 * terhadap isi XLSX sebelum ada penulisan ke basis data.
 */
class Pemetaan
{
    public const KOLOM = [
        'pengguna' => ['id_lama', 'email', 'peran', 'jabatan'],
        'ormawa' => ['id_lama', 'nama', 'slug', 'tingkat', 'buang'],
        'ruangan' => ['id_lama', 'kode_ruangan'],
        'wd' => ['kode_lama', 'kode_jabatan'],
    ];

    /** Peran lama OrmawaHub → peran baru (null = bukan akun). */
    public const PERAN = [
        'admin' => 'admin-persuratan', 'admin_custom' => 'operator-layanan', 'dekan' => 'dekan',
        'wd1' => 'wakil-dekan', 'wd2' => 'wakil-dekan', 'kasubag' => 'kasubag', 'kabag_umum' => 'kasubag', 'ormawa' => null,
    ];

    /** @var array<string, array<string, array<string, string>>> berkas => id kunci => baris */
    private array $data = [];

    public function __construct(private readonly string $direktori) {}

    public function ada(string $berkas): bool
    {
        return is_file($this->path($berkas));
    }

    /** @return array<string, string>|null */
    public function pengguna(string $idLama): ?array
    {
        return $this->baris('pengguna', 'id_lama')[$idLama] ?? null;
    }

    /** @return array<string, string>|null */
    public function ormawa(string $idLama): ?array
    {
        return $this->baris('ormawa', 'id_lama')[$idLama] ?? null;
    }

    public function ruangan(string $idLama): ?string
    {
        return ($this->baris('ruangan', 'id_lama')[$idLama]['kode_ruangan'] ?? '') ?: null;
    }

    public function jabatanWd(string $kodeLama): ?string
    {
        return ($this->baris('wd', 'kode_lama')[strtolower($kodeLama)]['kode_jabatan'] ?? '') ?: null;
    }

    public static function ya(mixed $nilai): bool
    {
        return in_array(strtolower(trim((string) $nilai)), ['ya', 'y', 'true', '1', 'yes'], true);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $sheet  sheet XLSX (tanpa baris contoh)
     * @return list<string> daftar masalah; kosong = lengkap
     */
    public function validasi(array $sheet, string $modeRuangan): array
    {
        $galat = [];

        foreach (['pengguna', 'ormawa', 'wd'] as $berkas) {
            $this->wajibAda($berkas, $galat);
        }
        $modeRuangan === 'aset_api' ? $this->wajibAda('ruangan', $galat) : null;

        if ($galat !== []) {
            return $galat;
        }

        $this->validasiWd($galat);
        $this->validasiPengguna($sheet['Users'] ?? [], $galat);
        $this->validasiOrmawa($sheet['Ormawa_Profiles'] ?? [], $galat);
        $this->validasiPengurus($sheet['Pengurus'] ?? [], $galat);
        $this->validasiRuangan($sheet['Rooms'] ?? [], $modeRuangan, $galat);

        return $galat;
    }

    /** @param  list<string>  $galat */
    private function wajibAda(string $berkas, array &$galat): void
    {
        if (! $this->ada($berkas)) {
            $galat[] = "Berkas pemetaan {$berkas}.csv tidak ditemukan.";

            return;
        }

        $header = SimpleExcelReader::create($this->path($berkas))->formatHeadersUsing(fn ($h) => strtolower(trim((string) $h)))->getHeaders() ?? [];
        $kurang = array_diff(self::KOLOM[$berkas], $header);

        if ($kurang !== []) {
            $galat[] = "{$berkas}.csv: kolom wajib hilang: ".implode(', ', $kurang).'.';
        }
    }

    /** @param  list<string>  $galat */
    private function validasiWd(array &$galat): void
    {
        foreach ($this->baris('wd', 'kode_lama') as $kode => $b) {
            if (! Jabatan::where('kode', $b['kode_jabatan'])->exists()) {
                $galat[] = "wd.csv: kode jabatan '{$b['kode_jabatan']}' (untuk {$kode}) tidak ada di master jabatan.";
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $users
     * @param  list<string>  $galat
     */
    private function validasiPengguna(array $users, array &$galat): void
    {
        $surel = [];

        foreach ($users as $u) {
            $id = $this->teks($u['id'] ?? null);
            $peranLama = strtolower((string) $this->teks($u['role'] ?? null));

            if ($id === null || PolaContoh::barisContoh($u) || ($peranLama !== '' && array_key_exists($peranLama, self::PERAN) && self::PERAN[$peranLama] === null)) {
                continue;
            }

            if (! array_key_exists($peranLama, self::PERAN)) {
                $galat[] = "Users {$id}: peran lama '{$peranLama}' tidak dikenal.";

                continue;
            }

            $m = $this->pengguna($id);

            if ($m === null) {
                $galat[] = "Users {$id}: tidak ada baris di pengguna.csv.";

                continue;
            }

            $cek = Validator::make(['email' => $m['email']], ['email' => ['required', 'email', new SurelDomainUnsil]]);

            if ($cek->fails()) {
                $galat[] = "pengguna.csv {$id}: surel '{$m['email']}' tidak sah (".$cek->errors()->first('email').')';
            } elseif (isset($surel[strtolower($m['email'])])) {
                $galat[] = "pengguna.csv: surel '{$m['email']}' dipakai ganda ({$surel[strtolower($m['email'])]} dan {$id}).";
            } else {
                $surel[strtolower($m['email'])] = $id;
                $ada = User::where('email', $m['email'])->first();

                if ($ada !== null && $ada->sumber_id_lama !== null && $ada->sumber_id_lama !== $id) {
                    $galat[] = "pengguna.csv {$id}: surel '{$m['email']}' sudah dipakai akun hasil impor lain ({$ada->sumber_id_lama}).";
                }
            }

            if (($m['peran'] ?? '') !== '' && ! Role::where('name', $m['peran'])->exists()) {
                $galat[] = "pengguna.csv {$id}: peran '{$m['peran']}' tidak ada.";
            }

            if (($m['jabatan'] ?? '') !== '' && ! Jabatan::where('kode', $m['jabatan'])->exists()) {
                $galat[] = "pengguna.csv {$id}: jabatan '{$m['jabatan']}' tidak ada.";
            }

            if (in_array($peranLama, ['wd1', 'wd2'], true) && ($m['jabatan'] ?? '') === '' && $this->jabatanWd($peranLama) === null) {
                $galat[] = "Users {$id}: peran {$peranLama} memerlukan baris di wd.csv atau kolom jabatan.";
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $profil
     * @param  list<string>  $galat
     */
    private function validasiOrmawa(array $profil, array &$galat): void
    {
        $slug = [];
        $nama = [];

        foreach ($profil as $p) {
            $id = $this->teks($p['id'] ?? null);

            if ($id === null || PolaContoh::barisContoh($p)) {
                continue;
            }

            $m = $this->ormawa($id);

            if ($m === null) {
                $galat[] = "Ormawa_Profiles {$id}: tidak ada baris di ormawa.csv.";

                continue;
            }

            if (self::ya($m['buang'] ?? '')) {
                continue;
            }

            if (! array_key_exists($m['tingkat'] ?? '', Ormawa::TINGKAT)) {
                $galat[] = "ormawa.csv {$id}: tingkat '".($m['tingkat'] ?? '')."' tidak sah (".implode('/', array_keys(Ormawa::TINGKAT)).').';
            }

            $namaBaru = ($m['nama'] ?? '') !== '' ? $m['nama'] : (string) $this->teks($p['nama'] ?? null);

            if ($namaBaru === '') {
                $galat[] = "ormawa.csv {$id}: nama kosong.";
            } elseif (isset($nama[mb_strtolower($namaBaru)])) {
                $galat[] = "ormawa.csv: nama '{$namaBaru}' dipakai ganda.";
            }
            $nama[mb_strtolower($namaBaru)] = true;

            if (($m['slug'] ?? '') !== '') {
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $m['slug']) !== 1) {
                    $galat[] = "ormawa.csv {$id}: slug '{$m['slug']}' tidak sah.";
                } elseif (isset($slug[$m['slug']])) {
                    $galat[] = "ormawa.csv: slug '{$m['slug']}' dipakai ganda.";
                }
                $slug[$m['slug']] = true;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pengurus
     * @param  list<string>  $galat
     */
    private function validasiPengurus(array $pengurus, array &$galat): void
    {
        foreach ($pengurus as $r) {
            $oid = $this->teks($r['ormawaId'] ?? null);

            if ($oid === null || PolaContoh::barisContoh($r)) {
                continue;
            }

            if ($this->ormawa($oid) === null) {
                $galat[] = 'Pengurus '.($this->teks($r['id'] ?? null) ?? '?').": ormawaId '{$oid}' tidak ada di ormawa.csv.";
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rooms
     * @param  list<string>  $galat
     */
    private function validasiRuangan(array $rooms, string $mode, array &$galat): void
    {
        $kode = [];

        foreach ($rooms as $r) {
            $id = $this->teks($r['id'] ?? null);

            if ($id === null || PolaContoh::barisContoh($r)) {
                continue;
            }

            $k = $this->ruangan($id);

            if ($k === null) {
                $mode === 'aset_api' ? $galat[] = "Rooms {$id}: tidak ada baris di ruangan.csv." : null;

                continue;
            }

            if (isset($kode[$k])) {
                $galat[] = "ruangan.csv: kode '{$k}' dipakai ganda.";
            }
            $kode[$k] = true;
        }
    }

    /** @return array<string, array<string, string>> */
    private function baris(string $berkas, string $kunci): array
    {
        if (! isset($this->data[$berkas])) {
            $this->data[$berkas] = [];

            if ($this->ada($berkas)) {
                foreach (SimpleExcelReader::create($this->path($berkas))->formatHeadersUsing(fn ($h) => strtolower(trim((string) $h)))->trimValues()->getRows() as $b) {
                    $b = array_map(fn ($v) => trim((string) $v), $b);
                    $k = strtolower($b[$kunci] ?? '');

                    if ($k !== '') {
                        $this->data[$berkas][$kunci === 'kode_lama' ? $k : ($b[$kunci])] = $b;
                    }
                }
            }
        }

        return $this->data[$berkas];
    }

    private function path(string $berkas): string
    {
        return rtrim($this->direktori, '/')."/{$berkas}.csv";
    }

    private function teks(mixed $nilai): ?string
    {
        $t = trim((string) $nilai);

        return $t === '' ? null : $t;
    }
}
