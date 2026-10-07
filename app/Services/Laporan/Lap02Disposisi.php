<?php

namespace App\Services\Laporan;

use App\Enums\StatusDisposisiPenerima;
use App\Models\DisposisiPenerima;
use Carbon\CarbonImmutable;

/** LAP-02: rekap disposisi per pejabat (terlambat, belum ditindaklanjuti). */
class Lap02Disposisi extends Laporan
{
    public function kode(): string
    {
        return 'lap-02';
    }

    public function judul(): string
    {
        return 'LAP-02 Rekap disposisi per pejabat';
    }

    public function deskripsi(): string
    {
        return 'Disposisi yang dibuat pada periode: jumlah diterima, selesai, terlambat, belum ditindaklanjuti, dan rata-rata hari penyelesaian per penerima.';
    }

    public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $penerima = DisposisiPenerima::whereHas('disposisi', fn ($q) => $q->whereBetween('created_at', [$dari, $sampai]))->with(['user:id,name', 'jabatan:id,nama', 'disposisi'])->get();

        $baris = $penerima->groupBy('user_id')->map(function ($kelompok) {
            $selesai = $kelompok->filter(fn (DisposisiPenerima $p) => $p->status === StatusDisposisiPenerima::Selesai);
            $terlambat = $kelompok->filter(fn (DisposisiPenerima $p) => $p->terlambat || ($p->status !== StatusDisposisiPenerima::Selesai && $p->disposisi->batas_waktu->lt(now())));
            $belum = $kelompok->filter(fn (DisposisiPenerima $p) => in_array($p->status, [StatusDisposisiPenerima::Diterima, StatusDisposisiPenerima::Dibaca], true));
            $durasi = $selesai->filter(fn (DisposisiPenerima $p) => $p->selesai_pada !== null)->map(fn (DisposisiPenerima $p) => $p->disposisi->created_at->diffInSeconds($p->selesai_pada, true));

            return [
                $kelompok->first()->user->name, $kelompok->pluck('jabatan.nama')->filter()->unique()->implode('; '),
                $kelompok->count(), $selesai->count(), $terlambat->count(), $belum->count(), $durasi->isEmpty() ? null : self::hari($durasi->avg()),
            ];
        })->sortBy(fn ($b) => $b[0])->values()->all();

        return [['judul' => 'Rekap per pejabat', 'kolom' => ['Pejabat', 'Jabatan', 'Diterima', 'Selesai', 'Terlambat', 'Belum ditindaklanjuti', 'Rata-rata hari selesai'], 'baris' => $baris]];
    }
}
