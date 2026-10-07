<?php

namespace App\Services\Ruangan;

use App\Contracts\LayananRuangan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Models\PermohonanRuangan;
use App\Support\SesiRuangan;
use Carbon\CarbonImmutable;

/** Satu definisi "ruangan terpakai": jadwal layanan (Aset/lokal) atau ditahan/dikonfirmasi permohonan lain. */
class KetersediaanRuangan
{
    public function __construct(private readonly LayananRuangan $layanan) {}

    /** @throws LayananRuanganTidakTersedia */
    public function terpakai(string $kode, string $tanggal, string $sesi, ?string $kecualiPermohonanId = null): bool
    {
        $hari = CarbonImmutable::parse($tanggal);

        $dilayanan = collect($this->layanan->jadwal($kode, $hari, $hari))
            ->contains(fn (array $j) => $j['tanggal'] === $tanggal && SesiRuangan::bentrok($sesi, $j['sesi']));

        if ($dilayanan) {
            return true;
        }

        return PermohonanRuangan::menguasai()
            ->where('kode_ruangan', $kode)->whereDate('tanggal', $tanggal)
            ->whereIn('sesi', SesiRuangan::yangBentrok($sesi))
            ->when($kecualiPermohonanId, fn ($q) => $q->where('permohonan_id', '!=', $kecualiPermohonanId))
            ->exists();
    }
}
