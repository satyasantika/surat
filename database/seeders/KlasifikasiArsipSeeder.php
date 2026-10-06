<?php

namespace Database\Seeders;

use App\Models\KlasifikasiArsip;
use Illuminate\Database\Seeder;

class KlasifikasiArsipSeeder extends Seeder
{
    /** Contoh awal; impor CSV pedoman klasifikasi arsip resmi untuk daftar lengkap. */
    public function run(): void
    {
        $data = [
            ['KM', 'Kemahasiswaan', null, null, null, null],
            ['KM.03', 'Pembinaan organisasi kemahasiswaan', 'KM', 2, 3, 'dinilai_kembali'],
            ['KM.03.02', 'Kegiatan organisasi kemahasiswaan', 'KM.03', 2, 3, 'musnah'],
            ['PK', 'Kepegawaian', null, null, null, null],
            ['PK.01', 'Pengadaan pegawai', 'PK', 5, 10, 'permanen'],
            ['UM', 'Umum', null, null, null, null],
        ];

        foreach ($data as [$kode, $nama, $induk, $aktif, $inaktif, $akhir]) {
            KlasifikasiArsip::updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'induk_id' => $induk ? KlasifikasiArsip::firstWhere('kode', $induk)?->id : null,
                'retensi_aktif_tahun' => $aktif,
                'retensi_inaktif_tahun' => $inaktif,
                'keterangan_akhir' => $akhir,
            ]);
        }
    }
}
