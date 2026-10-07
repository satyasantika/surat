<?php

namespace Database\Seeders;

use App\Models\RubrikLpj;
use Illuminate\Database\Seeder;

class RubrikLpjSeeder extends Seeder
{
    /** Rubrik lama (BR-17): dekan + dua WD menilai ketepatan & kepatuhan; kasubag menilai kelengkapan & kontribusi. Total maks 100. */
    public function run(): void
    {
        $pimpinan = ['dekan', 'wd-akademik', 'wd-kemahasiswaan'];

        $rubrik = [
            ['ketepatan', 'Ketepatan pelaksanaan', 20, $pimpinan, 1],
            ['kepatuhan', 'Kepatuhan tenggat', 5, $pimpinan, 2],
            ['kelengkapan', 'Kelengkapan laporan', 20, ['kasubag-umum'], 3],
            ['kontribusi_fakultas', 'Kontribusi bagi fakultas', 5, ['kasubag-umum'], 4],
        ];

        foreach ($rubrik as [$kode, $nama, $maks, $penilai, $urutan]) {
            RubrikLpj::updateOrCreate(['kode' => $kode], ['nama' => $nama, 'nilai_maks' => $maks, 'penilai_jabatan' => $penilai, 'urutan' => $urutan, 'aktif' => true]);
        }
    }
}
