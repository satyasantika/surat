<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BersihkanTmp extends Command
{
    protected $signature = 'surat:bersihkan-tmp {--jam=24 : Umur maksimum berkas (jam)}';

    protected $description = 'Menghapus berkas sementara di storage/app/tmp yang lebih tua dari batas (STANDAR-TEKNIS §1a.6)';

    public function handle(): int
    {
        $batas = now()->subHours((int) $this->option('jam'))->getTimestamp();
        $hapus = 0;

        foreach (File::files(storage_path('app/tmp'), true) as $berkas) {
            if ($berkas->getFilename() !== '.gitkeep' && $berkas->getMTime() < $batas) {
                File::delete($berkas->getPathname());
                $hapus++;
            }
        }

        $this->components->info("{$hapus} berkas sementara dihapus.");

        return self::SUCCESS;
    }
}
