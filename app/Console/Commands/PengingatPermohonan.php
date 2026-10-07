<?php

namespace App\Console\Commands;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengaturan;
use App\Support\Pengingat;
use Illuminate\Console\Command;

class PengingatPermohonan extends Command
{
    protected $signature = 'surat:pengingat-permohonan';

    protected $description = 'Mengingatkan pihak berikutnya bila permohonan tertahan lebih dari N hari di satu tahap (satu pengingat per pekan per tahap)';

    public function handle(NotifikasiAlur $alur): int
    {
        $hari = max(1, (int) Pengaturan::get('hari_tertahan_permohonan'));
        $batas = now()->subDays($hari);
        $terkirim = 0;

        Permohonan::whereNotIn('status', [StatusPermohonan::Selesai->value, StatusPermohonan::Ditolak->value, StatusPermohonan::Dibatalkan->value])
            ->with(['ormawa', 'riwayat'])->orderBy('id')
            ->each(function (Permohonan $p) use ($alur, $batas, &$terkirim) {
                $sejak = $p->riwayat->max('created_at') ?? $p->diajukan_pada;

                if ($sejak->gt($batas)) {
                    return;
                }

                $lama = (int) $sejak->diffInDays(now());
                Pengingat::sekali("permohonan:{$p->getKey()}:{$p->status->value}:".now()->format('o-\WW'), fn () => $alur->permohonanTertahan($p, $lama)) ? $terkirim++ : null;
            });

        $this->components->info("{$terkirim} pengingat permohonan tertahan dikirim.");

        return self::SUCCESS;
    }
}
