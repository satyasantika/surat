<?php

namespace App\Console\Commands;

use App\Enums\StatusDisposisiPenerima;
use App\Models\DisposisiPenerima;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\Pengaturan;
use App\Support\Pengingat;
use Illuminate\Console\Command;

class PengingatDisposisi extends Command
{
    protected $signature = 'surat:pengingat-disposisi';

    protected $description = 'Menandai disposisi terlambat (BR-05) dan mengingatkan penerima: mendekati batas waktu dan lewat (sekali per tahap)';

    public function handle(NotifikasiAlur $alur): int
    {
        $jam = max(1, (int) Pengaturan::get('jam_pengingat_disposisi'));
        $terlambat = $mendekati = 0;

        DisposisiPenerima::whereIn('status', [StatusDisposisiPenerima::Diterima->value, StatusDisposisiPenerima::Dibaca->value, StatusDisposisiPenerima::Ditindaklanjuti->value])
            ->whereHas('disposisi', fn ($q) => $q->where('batas_waktu', '<=', now()->addHours($jam)))
            ->with(['disposisi.suratMasuk', 'user'])->orderBy('id')
            ->each(function (DisposisiPenerima $p) use ($alur, &$terlambat, &$mendekati) {
                if ($p->disposisi->batas_waktu->lte(now())) {
                    $p->terlambat ?: $p->forceFill(['terlambat' => true])->saveQuietly();
                    Pengingat::sekali("disposisi:{$p->getKey()}:lewat", fn () => $alur->disposisiTerlambat($p)) ? $terlambat++ : null;
                } else {
                    Pengingat::sekali("disposisi:{$p->getKey()}:dekat", fn () => $alur->disposisiMendekati($p)) ? $mendekati++ : null;
                }
            });

        $this->components->info("{$terlambat} disposisi terlambat diingatkan, {$mendekati} mendekati batas diingatkan.");

        return self::SUCCESS;
    }
}
