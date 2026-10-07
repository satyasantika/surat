<?php

namespace App\Actions\Migrasi;

use App\Actions\Migrasi\Impor\ImporOrmawa;
use App\Actions\Migrasi\Impor\ImporPengguna;
use App\Actions\Migrasi\Impor\ImporPengurus;
use App\Actions\Migrasi\Impor\ImporRuangan;
use App\Exceptions\PemetaanTidakLengkap;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\Pemetaan;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Impor spreadsheet OrmawaHub (07-MIGRASI-DATA). Pemetaan divalidasi lengkap sebelum menulis; satu transaksi
 * per sheet; --dry-run menjalankan semuanya lalu rollback. Idempoten: baris yang sudah berhasil diimpor dilewati.
 */
class ImporOrmawaHub
{
    /** Urutan impor (§7) dan sheet wajib/opsional. */
    private const WAJIB = ['Users', 'Ormawa_Profiles', 'Pengurus'];

    private const OPSIONAL = ['Rooms', 'RektoratRooms'];

    public function jalankan(string $xlsx, string $direktoriPemetaan, bool $dryRun = false): KonteksImpor
    {
        if (! is_file($xlsx)) {
            throw new InvalidArgumentException("Berkas XLSX tidak ditemukan: {$xlsx}");
        }

        $sheet = $this->baca($xlsx);
        $mode = (string) Pengaturan::get('layanan_ruangan');
        $pemetaan = new Pemetaan($direktoriPemetaan);

        $galat = $pemetaan->validasi($sheet, $mode);

        if ($galat !== []) {
            throw new PemetaanTidakLengkap($galat);
        }

        $k = new KonteksImpor((string) Str::uuid(), $pemetaan, $mode);

        DB::beginTransaction();

        try {
            $langkah = [
                'Users' => fn ($b) => app(ImporPengguna::class)->jalankan($k, $b),
                'Ormawa_Profiles' => fn ($b) => app(ImporOrmawa::class)->jalankan($k, $b),
                'Pengurus' => fn ($b) => app(ImporPengurus::class)->jalankan($k, $b),
                'Rooms' => fn ($b) => app(ImporRuangan::class)->rooms($k, $b),
                'RektoratRooms' => fn ($b) => app(ImporRuangan::class)->rektorat($k, $b),
            ];

            foreach ($langkah as $nama => $impor) {
                if (isset($sheet[$nama])) {
                    DB::transaction(fn () => $impor($sheet[$nama]));
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $k;
    }

    /**
     * Seluruh sheet dibaca ke memori (data fakultas kecil). Baris contoh tidak disaring di sini agar log
     * mencatatnya sebagai "lewati".
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function baca(string $xlsx): array
    {
        $nama = SimpleExcelReader::create($xlsx)->getSheetNames();
        $hilang = array_diff(self::WAJIB, $nama);

        if ($hilang !== []) {
            throw new InvalidArgumentException('Sheet wajib tidak ada di XLSX: '.implode(', ', $hilang));
        }

        $hasil = [];

        foreach ([...self::WAJIB, ...self::OPSIONAL] as $s) {
            if (in_array($s, $nama, true)) {
                $hasil[$s] = SimpleExcelReader::create($xlsx)->fromSheetName($s)->trimHeaderRow()->getRows()->all();
            }
        }

        return $hasil;
    }
}
