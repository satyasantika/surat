<?php

namespace App\Services\Ruangan;

use App\Contracts\LayananRuangan;
use App\Exceptions\RuanganBentrok;
use App\Models\PemakaianRuanganLokal;
use App\Models\RuanganLokal;
use App\Support\KunciRuangan;
use App\Support\SesiRuangan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Layanan ruangan lokal (sebelum API Aset siap): ruangan_lokal dan pemakaian_ruangan_lokal. */
class LayananRuanganLokal implements LayananRuangan
{
    public function daftar(): array
    {
        return RuanganLokal::where('dalam_perawatan', false)->where('tampil_katalog', true)->orderBy('nama')->get()
            ->map(fn (RuanganLokal $r) => [
                'kode' => $r->kode, 'nama' => $r->nama, 'gedung' => $r->gedung, 'kapasitas' => $r->kapasitas, 'fasilitas' => $r->fasilitas,
            ])->all();
    }

    public function jadwal(string $kode, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $ruangan = RuanganLokal::where('kode', $kode)->first();

        if ($ruangan === null) {
            return [];
        }

        return PemakaianRuanganLokal::where('ruangan_lokal_id', $ruangan->getKey())
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('tanggal')->get()
            ->map(fn (PemakaianRuanganLokal $p) => ['tanggal' => $p->tanggal->toDateString(), 'sesi' => $p->sesi, 'keterangan' => $p->keterangan])
            ->all();
    }

    public function catatPemakaian(string $kode, string $tanggal, string $sesi, ?string $referensi = null, ?string $keterangan = null): string
    {
        $ruangan = RuanganLokal::where('kode', $kode)->first() ?? throw new InvalidArgumentException("Ruangan {$kode} tidak dikenal.");

        if (! SesiRuangan::valid($sesi)) {
            throw new InvalidArgumentException("Sesi {$sesi} tidak dikenal.");
        }

        return KunciRuangan::dengan([['kode' => $kode, 'tanggal' => $tanggal]], fn () => DB::transaction(function () use ($ruangan, $tanggal, $sesi, $referensi, $keterangan) {
            // Idempoten: referensi sama pada tanggal+sesi yang sama mengembalikan pemakaian yang ada.
            if ($referensi !== null) {
                $ada = PemakaianRuanganLokal::where('ruangan_lokal_id', $ruangan->getKey())->whereDate('tanggal', $tanggal)
                    ->where('sesi', $sesi)->where('permohonan_id', $referensi)->first();

                if ($ada !== null) {
                    return $ada->getKey();
                }
            }

            $bentrok = PemakaianRuanganLokal::where('ruangan_lokal_id', $ruangan->getKey())->whereDate('tanggal', $tanggal)
                ->whereIn('sesi', SesiRuangan::yangBentrok($sesi))
                ->when($referensi !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('permohonan_id')->orWhere('permohonan_id', '!=', $referensi)))
                ->exists();

            if ($bentrok) {
                throw new RuanganBentrok("Ruangan {$ruangan->nama} sudah dipakai pada {$tanggal} sesi {$sesi}.");
            }

            return PemakaianRuanganLokal::create([
                'ruangan_lokal_id' => $ruangan->getKey(), 'tanggal' => $tanggal, 'sesi' => $sesi,
                'keterangan' => $keterangan, 'permohonan_id' => $referensi,
            ])->getKey();
        }));
    }
}
