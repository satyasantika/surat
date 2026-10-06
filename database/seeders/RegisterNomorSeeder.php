<?php

namespace Database\Seeders;

use App\Models\RegisterNomor;
use Illuminate\Database\Seeder;

class RegisterNomorSeeder extends Seeder
{
    /** Pola awal; dapat diubah super-admin pada Master › Register nomor. */
    public function run(): void
    {
        $register = [
            ['agenda-masuk', 'Nomor agenda surat masuk', '{urut:4}/AGD/{tahun}'],
            ['naskah-dekan', 'Naskah dinas Dekan', '{urut}/{kode_unit}/{klasifikasi}/{tahun}'],
            ['sk-dekan', 'Keputusan Dekan', '{urut}/{kode_unit}/{klasifikasi}/{tahun}'],
            ['surat-tugas', 'Surat tugas', '{urut}/{kode_unit}/{klasifikasi}/{tahun}'],
            ['permohonan', 'Nomor permohonan ormawa', '{urut:4}/PMH/{bulan_romawi}/{tahun}'],
        ];

        foreach ($register as [$kode, $nama, $pola]) {
            RegisterNomor::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'pola' => $pola, 'reset' => 'tahunan', 'aktif' => true]);
        }
    }
}
