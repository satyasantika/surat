<?php

namespace App\Support;

use App\Models\Naskah;

/**
 * Konteks render naskah. Dari draf = data hidup (pratinjau); setelah ditandatangani bentuk yang sama
 * dibekukan sebagai snapshot dan render selalu bersumber dari snapshot (BR-08).
 */
class KonteksNaskah
{
    /** @return array<string, mixed> */
    public static function dariDraf(Naskah $naskah): array
    {
        $naskah->loadMissing(['jenis', 'penandaTanganJabatan', 'tujuan', 'tembusan', 'penandaTanganUser']);
        $jabatan = $naskah->penandaTanganJabatan;
        $pemangku = $naskah->penandaTanganUser ?? $jabatan->pemangkuPada($naskah->tanggal_naskah ?? now())?->user;

        return [
            'versi_templat' => $naskah->jenis->versi_templat,
            'templat' => $naskah->jenis->templat_blade,
            'kop' => Pengaturan::get('kop_surat'),
            'nomor' => $naskah->nomor,
            'tanggal' => $naskah->tanggal_naskah?->toDateString(),
            'jenis' => ['kode' => $naskah->jenis->kode, 'nama' => $naskah->jenis->nama],
            'perihal' => $naskah->perihal,
            'sifat' => $naskah->derajat_kecepatan->label(),
            'klasifikasi_keamanan' => $naskah->klasifikasi_keamanan->value,
            'data' => $naskah->data,
            'variabel' => $naskah->jenis->variabel,
            'isi' => $naskah->isi,
            'tujuan' => $naskah->tujuan->pluck('nama')->all(),
            'tembusan' => $naskah->tembusan->pluck('nama')->all(),
            'penanda_tangan' => [
                'nama' => $pemangku?->name,
                'nip' => $pemangku?->nip_nim,
                'jabatan' => $jabatan->nama,
            ],
            'atas_nama' => $naskah->atas_nama,
            'mode' => $naskah->mode_tanda_tangan,
            'status' => $naskah->status->value,
        ];
    }
}
