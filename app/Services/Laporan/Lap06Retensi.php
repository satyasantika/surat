<?php

namespace App\Services\Laporan;

use App\Services\Arsip\Retensi;
use Carbon\CarbonImmutable;

/** LAP-06: arsip yang melewati retensi aktif/inaktif per klasifikasi (acuan: akhir periode). */
class Lap06Retensi extends Laporan
{
    public function kode(): string
    {
        return 'lap-06';
    }

    public function judul(): string
    {
        return 'LAP-06 Arsip melewati retensi';
    }

    public function deskripsi(): string
    {
        return 'Arsip (surat masuk diarsipkan dan naskah terbit) yang melewati retensi aktif atau inaktif pada tanggal akhir periode. Tidak ada penghapusan otomatis; pemusnahan melalui prosedur resmi dan dicatat manual dengan nomor berita acara.';
    }

    public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $baris = array_map(fn ($b) => [$b['klasifikasi'], $b['jenis'], $b['nomor'], $b['tanggal'], $b['batas_aktif'], $b['batas_inaktif'], Retensi::labelTahap($b['tahap']), $b['nasib_akhir']], Retensi::tinjau($sampai));

        return [['judul' => 'Arsip melewati retensi', 'kolom' => ['Klasifikasi', 'Jenis', 'Nomor', 'Tanggal dasar', 'Batas aktif', 'Batas inaktif', 'Tahap', 'Nasib akhir'], 'baris' => $baris]];
    }
}
