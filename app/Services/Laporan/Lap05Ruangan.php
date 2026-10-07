<?php

namespace App\Services\Laporan;

use App\Models\PermohonanRuangan;
use Carbon\CarbonImmutable;

/** LAP-05: pemakaian ruangan oleh ormawa (permohonan dikonfirmasi). */
class Lap05Ruangan extends Laporan
{
    public function kode(): string
    {
        return 'lap-05';
    }

    public function judul(): string
    {
        return 'LAP-05 Pemakaian ruangan oleh ormawa';
    }

    public function deskripsi(): string
    {
        return 'Pemakaian ruangan yang dikonfirmasi pada periode, per bulan, ruangan, dan ormawa.';
    }

    public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $baris = PermohonanRuangan::where('status', PermohonanRuangan::DIKONFIRMASI)->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->with('permohonan.ormawa:id,nama')->get()
            ->groupBy(fn (PermohonanRuangan $r) => $r->tanggal->format('Y-m').'|'.$r->kode_ruangan.' — '.$r->nama_ruangan.'|'.$r->permohonan->ormawa->nama)
            ->map(fn ($k, $kunci) => [...explode('|', $kunci, 3), $k->count(), $k->pluck('tanggal')->map->toDateString()->unique()->count()])
            ->sortKeys()->values()->all();

        return [['judul' => 'Pemakaian per bulan, ruangan, dan ormawa', 'kolom' => ['Bulan', 'Ruangan', 'Ormawa', 'Jumlah sesi', 'Jumlah hari'], 'baris' => $baris]];
    }
}
