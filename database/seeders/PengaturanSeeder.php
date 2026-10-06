<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Support\Pengaturan as PengaturanSistem;
use Illuminate\Database\Seeder;

class PengaturanSeeder extends Seeder
{
    /** Hanya mengisi kunci yang belum ada; nilai yang sudah diubah admin tidak ditimpa. */
    public function run(): void
    {
        foreach (PengaturanSistem::BAWAAN as $kunci => $isi) {
            Pengaturan::firstOrCreate(['kunci' => $kunci], ['nilai' => $isi['nilai'], 'grup' => $isi['grup']]);
        }
    }
}
