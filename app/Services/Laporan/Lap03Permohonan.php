<?php

namespace App\Services\Laporan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\RiwayatPermohonan;
use Carbon\CarbonImmutable;

/** LAP-03: rekap permohonan per bulan/ormawa/status dan rata-rata waktu per tahap (dari riwayat_permohonan). */
class Lap03Permohonan extends Laporan
{
    public function kode(): string
    {
        return 'lap-03';
    }

    public function judul(): string
    {
        return 'LAP-03 Rekap permohonan ormawa';
    }

    public function deskripsi(): string
    {
        return 'Permohonan yang diajukan pada periode: jumlah per bulan, ormawa, dan status; serta rata-rata waktu yang dihabiskan pada setiap tahap.';
    }

    public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $permohonan = Permohonan::whereBetween('diajukan_pada', [$dari, $sampai])->with('ormawa:id,nama')->get();

        $rekap = $permohonan->groupBy(fn (Permohonan $p) => $p->diajukan_pada->format('Y-m').'|'.$p->ormawa->nama.'|'.$p->status->value)
            ->map(fn ($k, $kunci) => [...explode('|', $kunci, 3), $k->count()])->sortKeys()
            ->map(fn ($b) => [$b[0], $b[1], StatusPermohonan::from($b[2])->label(), $b[3]])->values()->all();

        // Waktu pada tahap = selisih antar-riwayat berurutan, dihitung ke tahap sebelumnya (ke_status baris terdahulu).
        $durasi = [];

        RiwayatPermohonan::whereIn('permohonan_id', $permohonan->pluck('id'))->orderBy('permohonan_id')->orderBy('created_at')->get(['permohonan_id', 'ke_status', 'created_at'])
            ->groupBy('permohonan_id')->each(function ($riwayat) use (&$durasi) {
                foreach ($riwayat->values() as $i => $r) {
                    if (isset($riwayat[$i + 1])) {
                        $durasi[$r->ke_status][] = $r->created_at->diffInSeconds($riwayat[$i + 1]->created_at, true);
                    }
                }
            });

        $perTahap = collect($durasi)->map(fn ($d, $status) => [StatusPermohonan::tryFrom($status)?->label() ?? $status, count($d), self::hari(array_sum($d) / count($d)), self::hari(max($d))])->sortBy(fn ($b) => $b[0])->values()->all();

        return [
            ['judul' => 'Jumlah per bulan, ormawa, dan status', 'kolom' => ['Bulan', 'Ormawa', 'Status', 'Jumlah'], 'baris' => $rekap],
            ['judul' => 'Waktu per tahap (hari)', 'kolom' => ['Tahap', 'Jumlah transisi', 'Rata-rata (hari)', 'Terlama (hari)'], 'baris' => $perTahap],
        ];
    }
}
