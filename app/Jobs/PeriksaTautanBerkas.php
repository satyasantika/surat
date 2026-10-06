<?php

namespace App\Jobs;

use App\Models\TautanBerkas;
use App\Services\Berkas\PemeriksaTautan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PeriksaTautanBerkas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public TautanBerkas $tautan)
    {
        $this->onQueue('tautan');
    }

    public function handle(PemeriksaTautan $pemeriksa): void
    {
        $pemeriksa->periksa($this->tautan->fresh() ?? $this->tautan);
    }
}
