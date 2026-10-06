<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\UnitKerja;
use Illuminate\Database\Seeder;

class StrukturFkipSeeder extends Seeder
{
    public function run(): void
    {
        $fakultas = UnitKerja::updateOrCreate(
            ['kode' => 'UN58.10'],
            ['nama' => 'Fakultas Keguruan dan Ilmu Pendidikan', 'aktif' => true],
        );

        $jabatan = [
            ['dekan', 'Dekan', null, true, 1],
            ['wd-akademik', 'Wakil Dekan Bidang Akademik', 'akademik', true, 2],
            ['wd-umum-keuangan', 'Wakil Dekan Bidang Umum dan Keuangan', 'umum_keuangan', true, 3],
            ['wd-kemahasiswaan', 'Wakil Dekan Bidang Kemahasiswaan', 'kemahasiswaan', true, 4],
            ['kasubag-umum', 'Kepala Subbagian Umum', null, false, 5],
        ];

        foreach ($jabatan as [$kode, $nama, $bidang, $ttd, $urutan]) {
            Jabatan::updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'unit_kerja_id' => $fakultas->id,
                'bidang' => $bidang,
                'dapat_menandatangani' => $ttd,
                'urutan' => $urutan,
            ]);
        }
    }
}
