<?php

namespace App\Console\Commands;

use App\Models\Lpj;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengingat;
use Illuminate\Console\Command;

class PengingatLpj extends Command
{
    protected $signature = 'surat:pengingat-lpj';

    protected $description = 'Mengingatkan ormawa tentang LPJ yang belum diajukan: H-3, hari H, dan terlambat (mingguan)';

    public function handle(NotifikasiAlur $alur): int
    {
        $terkirim = 0;
        $hariIni = now()->startOfDay();

        Lpj::where('status', Lpj::DRAF)->whereDate('batas_waktu', '<=', $hariIni->copy()->addDays(3))->with('permohonan.ormawa')->orderBy('id')
            ->each(function (Lpj $lpj) use ($alur, $hariIni, &$terkirim) {
                $sisa = (int) $hariIni->diffInDays($lpj->batas_waktu->startOfDay(), false);

                $tahap = match (true) {
                    $sisa === 3 => 'h-3', $sisa === 0 => 'h', $sisa < 0 => 'lewat', default => null,
                };

                if ($tahap === null) {
                    return;
                }

                $kunci = "lpj:{$lpj->getKey()}:".($tahap === 'lewat' ? 'lewat:'.now()->format('o-\WW') : $tahap);
                Pengingat::sekali($kunci, fn () => $alur->lpjPengingat($lpj, $tahap)) ? $terkirim++ : null;
            });

        $this->components->info("{$terkirim} pengingat LPJ dikirim.");

        return self::SUCCESS;
    }
}
