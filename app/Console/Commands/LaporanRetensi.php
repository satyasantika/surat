<?php

namespace App\Console\Commands;

use App\Services\Arsip\Retensi;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengingat;
use Illuminate\Console\Command;

class LaporanRetensi extends Command
{
    protected $signature = 'surat:laporan-retensi {--ulang : Kirim ulang walau bulan ini sudah dikirim}';

    protected $description = 'Laporan bulanan arsip yang melewati retensi aktif/inaktif (RS-09) → notifikasi admin; tidak menghapus apa pun';

    public function handle(NotifikasiAlur $alur): int
    {
        $daftar = Retensi::tinjau();
        $inaktif = count(array_filter($daftar, fn ($b) => $b['tahap'] === Retensi::INAKTIF_LEWAT));
        $aktif = count($daftar) - $inaktif;

        $this->components->info(count($daftar)." arsip melewati retensi ({$aktif} masuk masa inaktif, {$inaktif} melewati retensi inaktif).");

        if ($daftar === []) {
            return self::SUCCESS;
        }

        $kunci = 'retensi:'.now()->format('Y-m').($this->option('ulang') ? ':'.now()->timestamp : '');
        Pengingat::sekali($kunci, fn () => $alur->retensiArsip(count($daftar), $aktif, $inaktif));

        return self::SUCCESS;
    }
}
