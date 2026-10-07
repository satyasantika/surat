<?php

namespace App\Listeners;

use App\Actions\Permohonan\TransisiPermohonan;
use App\Enums\StatusPermohonan;
use App\Events\NaskahTerbit;
use App\Jobs\CatatPemakaianRuangan;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Services\Ruangan\KetersediaanRuangan;
use App\Support\KunciRuangan;

/**
 * Surat izin terbit → permohonan selesai, ruangan dikonfirmasi setelah cek bentrok ulang di dalam kunci, lalu
 * pemakaian dicatat ke layanan ruangan lewat antrean integrasi (idempoten).
 */
class SelesaikanPermohonanDariNaskah
{
    public function __construct(private readonly TransisiPermohonan $transisi, private readonly KetersediaanRuangan $ketersediaan) {}

    public function handle(NaskahTerbit $event): void
    {
        $naskah = $event->naskah;
        $permohonan = $naskah->permohonan_id ? Permohonan::find($naskah->permohonan_id) : null;

        // Hanya surat izin permohonan itu sendiri (bukan pengantar rektorat) yang menyelesaikan permohonan.
        if ($permohonan === null || $permohonan->naskah_izin_id !== $naskah->getKey() || $permohonan->status !== StatusPermohonan::Penerbitan) {
            return;
        }

        $butir = $permohonan->ruangan()->get()->map(fn (PermohonanRuangan $r) => ['kode' => $r->kode_ruangan, 'tanggal' => $r->tanggal->toDateString()])->all();

        $selesai = KunciRuangan::dengan($butir, fn () => $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($naskah) {
            if ($segar->status !== StatusPermohonan::Penerbitan) {
                return false;
            }

            $baris = $segar->ruangan()->where('status', PermohonanRuangan::DITAHAN)->get();

            foreach ($baris as $r) {
                if ($this->ketersediaan->terpakai($r->kode_ruangan, $r->tanggal->toDateString(), $r->sesi, $segar->getKey())) {
                    activity('permohonan')->event('bentrok-penerbitan')->performedOn($segar)
                        ->log("Ruangan {$r->nama_ruangan} {$r->tanggal->toDateString()} sesi {$r->sesi} bentrok saat konfirmasi; ditinjau admin.");

                    return false;
                }
            }

            $segar->ruangan()->where('status', PermohonanRuangan::DITAHAN)->update(['status' => PermohonanRuangan::DIKONFIRMASI]);
            $this->transisi->ke($segar, StatusPermohonan::Selesai, $naskah->penandaTanganUser ?? $naskah->penyusun, "Surat izin {$naskah->nomor} terbit");

            return true;
        }));

        if ($selesai) {
            CatatPemakaianRuangan::dispatch($permohonan)->afterCommit();
        }
    }
}
