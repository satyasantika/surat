<?php

namespace Database\Seeders;

use App\Models\JenisNaskah;
use App\Models\JenisPermohonan;
use Illuminate\Database\Seeder;

class JenisPermohonanSeeder extends Seeder
{
    public function run(): void
    {
        $izin = JenisNaskah::firstWhere('kode', 'surat-izin-kegiatan')?->id;
        $pengantar = JenisNaskah::firstWhere('kode', 'pengantar-proposal')?->id;
        $berkas = ['surat_permohonan', 'proposal'];

        $jenis = [
            ['kegiatan', 'Izin kegiatan', false, false, $izin, $berkas],
            ['kegiatan-ruangan', 'Izin kegiatan dan peminjaman ruangan', true, false, $izin, $berkas],
            ['kegiatan-ruangan-rektorat', 'Izin kegiatan, ruangan, dan fasilitas rektorat', true, true, $izin, $berkas],
            ['pengantar-proposal', 'Pengantar proposal', false, false, $pengantar, ['proposal']],
        ];

        foreach ($jenis as [$kode, $nama, $ruangan, $rektorat, $naskah, $wajib]) {
            JenisPermohonan::updateOrCreate(['kode' => $kode], [
                'nama' => $nama, 'butuh_ruangan' => $ruangan, 'butuh_fasilitas_rektorat' => $rektorat,
                'jenis_naskah_id' => $naskah, 'berkas_wajib' => $wajib, 'aktif' => true,
            ]);
        }
    }
}
