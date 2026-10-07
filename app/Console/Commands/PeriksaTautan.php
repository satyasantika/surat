<?php

namespace App\Console\Commands;

use App\Jobs\PeriksaTautanBerkas;
use App\Models\TautanBerkas;
use Illuminate\Console\Command;

class PeriksaTautan extends Command
{
    protected $signature = 'surat:periksa-tautan {--hari=6 : Lewati tautan yang dicek dalam N hari terakhir} {--batas=1000 : Maksimum tautan per putaran}';

    protected $description = 'Memeriksa ulang keteraksesan tautan berkas (antrean tautan); admin diberi tahu saat tautan berubah menjadi tidak dapat diakses';

    public function handle(): int
    {
        $dispatch = 0;

        TautanBerkas::where(fn ($q) => $q->whereNull('dicek_pada')->orWhere('dicek_pada', '<', now()->subDays((int) $this->option('hari'))))
            ->orderByRaw('dicek_pada IS NOT NULL')->orderBy('dicek_pada')->limit((int) $this->option('batas'))->get()
            ->each(function (TautanBerkas $t) use (&$dispatch) {
                PeriksaTautanBerkas::dispatch($t);
                $dispatch++;
            });

        $this->components->info("{$dispatch} tautan dijadwalkan untuk diperiksa.");

        return self::SUCCESS;
    }
}
