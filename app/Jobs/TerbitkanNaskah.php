<?php

namespace App\Jobs;

use App\Actions\Naskah\TransisiNaskah;
use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\User;
use App\Services\Naskah\RenderNaskah;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Menerbitkan naskah yang sudah ditandatangani: render PDF dari snapshot, hitung SHA-256, status terbit. Idempoten. */
class TerbitkanNaskah implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Naskah $naskah)
    {
        $this->onQueue('pdf');
    }

    public function handle(RenderNaskah $render, TransisiNaskah $transisi): void
    {
        $transisi->dalamKunci($this->naskah, function (Naskah $segar) use ($render, $transisi) {
            if ($segar->status !== StatusNaskah::Ditandatangani || $segar->hash_pdf !== null) {
                return; // sudah terbit/dibatalkan: tidak ada yang dikerjakan
            }

            $segar->hash_pdf = RenderNaskah::hash($render->pdf($segar));
            $segar->diterbitkan_pada = now();

            /** @var User $pelaku */
            $pelaku = $segar->penandaTanganUser ?? $segar->penyusun;
            $transisi->ke($segar, StatusNaskah::Terbit, $pelaku, 'Diterbitkan; hash PDF dicatat');
        });
    }
}
