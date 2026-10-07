<?php

namespace App\Jobs;

use App\Contracts\LayananRuangan;
use App\Exceptions\RuanganBentrok;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Mencatat pemakaian ruangan terkonfirmasi ke layanan (Aset/lokal). Idempoten: baris yang sudah punya
 * id_pemakaian_aset dilewati dan layanan memakai id permohonan sebagai referensi. Gagal → retry; habis → notifikasi admin.
 */
class CatatPemakaianRuangan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(public Permohonan $permohonan)
    {
        $this->onQueue('integrasi');
    }

    public function handle(LayananRuangan $layanan): void
    {
        $baris = PermohonanRuangan::where('permohonan_id', $this->permohonan->getKey())
            ->where('status', PermohonanRuangan::DIKONFIRMASI)->whereNull('id_pemakaian_aset')->get();

        foreach ($baris as $r) {
            try {
                $id = $layanan->catatPemakaian($r->kode_ruangan, $r->tanggal->toDateString(), $r->sesi, $this->permohonan->getKey(), "Permohonan {$this->permohonan->nomor}");
            } catch (RuanganBentrok $e) {
                // Bentrok dengan pemakaian lain di layanan: tidak ada gunanya diulang; beri tahu admin.
                $this->beritahuAdmin("Pemakaian {$r->nama_ruangan} {$r->tanggal->toDateString()} sesi {$r->sesi} bentrok di layanan ruangan: {$e->getMessage()}");
                $this->fail($e);

                return;
            }

            $r->forceFill(['id_pemakaian_aset' => $id !== '' ? $id : 'dicatat'])->save();
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->beritahuAdmin("Pencatatan pemakaian ruangan permohonan {$this->permohonan->nomor} gagal: ".($e?->getMessage() ?? 'tidak diketahui'));
    }

    private function beritahuAdmin(string $pesan): void
    {
        activity('permohonan')->event('pemakaian-gagal')->performedOn($this->permohonan)->log($pesan);

        foreach (User::permission('permohonan.validasi')->get() as $admin) {
            Notification::make()->title('Pencatatan ruangan perlu perhatian')->body($pesan)->danger()->sendToDatabase($admin);
        }
    }
}
