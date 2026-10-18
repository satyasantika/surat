<?php

namespace App\Jobs;

use App\Contracts\GerbangWhatsapp;
use App\Mail\HasilSuratKePengirim;
use App\Models\SuratMasuk;
use App\Notifications\Saluran\SaluranWhatsapp;
use App\Support\Pengaturan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Memberi tahu pengirim surat masuk (eksternal/prodi tanpa akun) bahwa hasil sudah tersedia, beserta tautan lacak.
 *
 * Kontak pengirim terenkripsi dan hanya didekripsi di sini; job hanya membawa id surat sehingga kontak tidak masuk
 * muatan antrean (tabel jobs/failed_jobs), log, maupun jejak audit. Kegagalan dicatat tanpa pesan galat (pesan
 * pustaka surel dapat memuat alamat tujuan).
 */
class KirimHasilKePengirim implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $suratMasukId)
    {
        $this->onQueue('default');
    }

    public function handle(GerbangWhatsapp $gerbang): void
    {
        $surat = SuratMasuk::find($this->suratMasukId);

        if ($surat === null || $surat->hasil_dikirim_pada === null) {
            return;
        }

        $tautan = $surat->tautanLacak();

        if (filled($surat->pengirim_surel)) {
            try {
                Mail::to($surat->pengirim_surel)->send(new HasilSuratKePengirim($surat->pengirim_nama, $tautan));
            } catch (Throwable $e) {
                Log::warning('Surel hasil ke pengirim gagal: '.get_class($e).' (surat '.$this->suratMasukId.')');
            }
        }

        $nomor = filled($surat->pengirim_wa) ? SaluranWhatsapp::normalisasi((string) $surat->pengirim_wa) : null;

        if ($nomor !== null && Pengaturan::get('wa_aktif') && $gerbang->tersedia()) {
            $gerbang->kirim($nomor, "Surat Anda kepada Dekan FKIP Unsil telah selesai diproses. Lihat hasilnya: {$tautan}");
        }
    }
}
