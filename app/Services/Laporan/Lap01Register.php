<?php

namespace App\Services\Laporan;

use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\SuratMasuk;
use Carbon\CarbonImmutable;

/** LAP-01: register surat masuk dan keluar per periode. */
class Lap01Register extends Laporan
{
    public function kode(): string
    {
        return 'lap-01';
    }

    public function judul(): string
    {
        return 'LAP-01 Register surat masuk dan keluar';
    }

    public function deskripsi(): string
    {
        return 'Surat masuk (menurut tanggal terima) dan naskah keluar bernomor (menurut tanggal naskah) pada periode.';
    }

    public function susun(CarbonImmutable $dari, CarbonImmutable $sampai): array
    {
        $kolom = ['Nomor', 'Tanggal', 'Asal / Tujuan', 'Perihal', 'Klasifikasi', 'Status'];

        $masuk = SuratMasuk::whereBetween('tanggal_terima', [$dari, $sampai])->orderBy('tanggal_terima')->orderBy('nomor_agenda')->get()
            ->map(fn (SuratMasuk $s) => [$s->nomor_agenda, $s->tanggal_terima->format('Y-m-d'), $s->asal, $this->perihalAman($s->klasifikasi_keamanan, $s->perihal), $s->klasifikasi_keamanan->label(), $s->status->label()])->all();

        $keluar = Naskah::whereIn('status', [StatusNaskah::Terbit->value, StatusNaskah::Ditandatangani->value, StatusNaskah::Dibatalkan->value])
            ->whereNotNull('nomor')->whereBetween('tanggal_naskah', [$dari->toDateString(), $sampai->toDateString()])
            ->with('tujuan')->orderBy('tanggal_naskah')->orderBy('nomor')->get()
            ->map(fn (Naskah $n) => [$n->nomor, $n->tanggal_naskah?->format('Y-m-d'), $n->tujuan->pluck('nama')->implode('; '), $this->perihalAman($n->klasifikasi_keamanan, $n->perihal), $n->klasifikasi_keamanan->label(), $n->status->label()])->all();

        return [
            ['judul' => 'Surat masuk', 'kolom' => $kolom, 'baris' => $masuk],
            ['judul' => 'Surat keluar', 'kolom' => $kolom, 'baris' => $keluar],
        ];
    }
}
