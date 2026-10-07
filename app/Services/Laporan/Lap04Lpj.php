<?php

namespace App\Services\Laporan;

use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\RubrikLpj;
use Carbon\CarbonImmutable;

/** LAP-04: rekap LPJ dan nilai per ormawa (bahan pembinaan dan kriteria kemahasiswaan akreditasi). */
class Lap04Lpj extends Laporan
{
    public function kode(): string
    {
        return 'lap-04';
    }

    public function judul(): string
    {
        return 'LAP-04 Rekap LPJ dan nilai per ormawa';
    }

    public function deskripsi(): string
    {
        return 'LPJ atas kegiatan yang selesai pada periode: jumlah, diajukan, dinilai, terlambat, rata-rata nilai akhir, dan rata-rata per aspek rubrik.';
    }

    public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $rubrik = RubrikLpj::where('aktif', true)->orderBy('urutan')->get();
        $lpj = Lpj::whereHas('permohonan', fn ($q) => $q->whereBetween('tanggal_selesai', [$dari->toDateString(), $sampai->toDateString()]))
            ->with(['permohonan.ormawa:id,nama,tingkat', 'nilai'])->get();

        $baris = $lpj->groupBy(fn (Lpj $l) => $l->permohonan->ormawa_id)->map(function ($k) use ($rubrik) {
            $ormawa = $k->first()->permohonan->ormawa;
            $dinilai = $k->where('status', Lpj::DINILAI);
            $nilai = $k->flatMap->nilai;

            return [
                $ormawa->nama, Ormawa::TINGKAT[$ormawa->tingkat] ?? $ormawa->tingkat, $k->count(),
                $k->whereIn('status', [Lpj::DIAJUKAN, Lpj::DINILAI])->count(), $dinilai->count(), $k->filter(fn (Lpj $l) => $l->terlambat())->count(),
                $dinilai->isEmpty() ? null : round((float) $dinilai->avg('nilai_akhir'), 2),
                ...$rubrik->map(function (RubrikLpj $r) use ($nilai) {
                    $n = $nilai->where('rubrik_lpj_id', $r->getKey());

                    return $n->isEmpty() ? null : round((float) $n->avg('nilai'), 2);
                })->all(),
            ];
        })->sortBy(fn ($b) => $b[0])->values()->all();

        return [[
            'judul' => 'Rekap per ormawa',
            'kolom' => ['Ormawa', 'Tingkat', 'LPJ', 'Diajukan', 'Dinilai', 'Terlambat', 'Rata-rata nilai akhir', ...$rubrik->map(fn (RubrikLpj $r) => "{$r->nama} (maks {$r->nilai_maks})")->all()],
            'baris' => $baris,
        ]];
    }
}
