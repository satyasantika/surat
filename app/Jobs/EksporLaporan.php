<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Laporan\DaftarLaporan;
use App\Services\Register\EksporXlsx;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/** Menyusun laporan ke XLSX sementara (storage/app/tmp, ≤ 24 jam) dan memberi tahu pemohon dengan tautan unduh bertanda tangan. */
class EksporLaporan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $kode, public string $dari, public string $sampai, public string $penggunaId)
    {
        $this->onQueue('default');
    }

    public function handle(EksporXlsx $ekspor): void
    {
        $pengguna = User::find($this->penggunaId);
        $laporan = DaftarLaporan::cari($this->kode);

        // Izin diperiksa ulang di sisi pekerja: pengguna dapat saja kehilangan hak sejak permintaan dibuat.
        if ($pengguna === null || $laporan === null || ! $pengguna->can('laporan.lihat')) {
            return;
        }

        $nama = $ekspor->tulisTabel(
            $laporan->susun(CarbonImmutable::parse($this->dari)->startOfDay(), CarbonImmutable::parse($this->sampai)->endOfDay()),
            $pengguna, $laporan->kode(),
        );

        Notification::make()->title('Laporan siap diunduh')->body($laporan->judul().' ('.$this->dari.' s.d. '.$this->sampai.'). Berkas dihapus otomatis dalam 24 jam.')->success()
            ->actions([Action::make('unduh')->label('Unduh XLSX')->url(URL::temporarySignedRoute('ekspor.unduh', now()->addHours(2), ['berkas' => $nama]), shouldOpenInNewTab: true)->markAsRead()])
            ->sendToDatabase($pengguna);
    }
}
