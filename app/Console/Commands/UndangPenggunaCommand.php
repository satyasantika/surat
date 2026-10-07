<?php

namespace App\Console\Commands;

use App\Actions\Migrasi\UndangPengguna;
use Illuminate\Console\Command;

class UndangPenggunaCommand extends Command
{
    protected $signature = 'ormawahub:undang-pengguna {--surel= : Undang satu akun saja} {--dry-run : Hitung tanpa mengirim}';

    protected $description = 'Mengirim undangan atur kata sandi (antrean) ke akun hasil migrasi OrmawaHub; tidak pernah dua kali per akun';

    public function handle(UndangPengguna $undang): int
    {
        $hasil = $undang->jalankan((bool) $this->option('dry-run'), $this->option('surel') ?: null);

        $this->components->info(($this->option('dry-run') ? '[dry-run] ' : '')."{$hasil['diundang']} akun diundang, {$hasil['dilewati']} dilewati (sudah diundang/nonaktif).");

        return self::SUCCESS;
    }
}
