<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Tidak ada akun bawaan (S-03): pengguna pertama dibuat lewat `php artisan db:seed --class=...`
     * atau perintah khusus, bukan lewat seeder.
     */
    public function run(): void
    {
        $this->call([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, KlasifikasiArsipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class, JenisPermohonanSeeder::class]);
    }
}
