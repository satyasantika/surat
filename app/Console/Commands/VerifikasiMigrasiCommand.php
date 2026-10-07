<?php

namespace App\Console\Commands;

use App\Actions\Migrasi\VerifikasiMigrasi;
use Illuminate\Console\Command;

class VerifikasiMigrasiCommand extends Command
{
    protected $signature = 'ormawahub:verifikasi';

    protected $description = 'Verifikasi hasil migrasi OrmawaHub (07-MIGRASI-DATA §8); kode keluar ≠ 0 bila ada cek gagal';

    public function handle(VerifikasiMigrasi $verifikasi): int
    {
        $hasil = $verifikasi->jalankan();

        $this->table(['Cek', 'Hasil', 'Rincian'], array_map(fn ($h) => [$h['cek'], $h['ok'] ? 'LULUS' : 'GAGAL', $h['rincian']], $hasil));

        $gagal = count(array_filter($hasil, fn ($h) => ! $h['ok']));
        $gagal === 0 ? $this->components->info('Semua cek lulus.') : $this->components->error("{$gagal} cek gagal; perbaiki sebelum potong.");

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
