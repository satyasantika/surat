<?php

namespace App\Console\Commands;

use App\Models\Ormawa;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Console\Command;

class TandaiBlokirLpj extends Command
{
    protected $signature = 'surat:tandai-blokir-lpj';

    protected $description = 'Menyelaraskan penanda blokir pengajuan ormawa dengan LPJ terlambat dan kebijakan (BR-16)';

    public function handle(NotifikasiAlur $alur): int
    {
        $diblokir = $dibuka = 0;

        Ormawa::where('aktif', true)->orderBy('id')->each(function (Ormawa $o) use ($alur, &$diblokir, &$dibuka) {
            $hasil = $o->segarkanBlokirLpj();

            if ($hasil !== null) {
                $alur->ormawaBlokirBerubah($o, $hasil === 'diblokir');
                $hasil === 'diblokir' ? $diblokir++ : $dibuka++;
            }
        });

        $this->components->info("{$diblokir} ormawa diblokir, {$dibuka} dibuka.");

        return self::SUCCESS;
    }
}
