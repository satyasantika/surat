<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Uat\DataUat;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Akun demo per peran + data REKAAN untuk tangkapan layar panduan (F12.4). Hanya APP_ENV=local; akun berdomain contoh.test,
 * kata sandi dari PANDUAN_PASSWORD (tidak pernah di repo), dikecualikan dari MFA hanya di lokal (config panduan.tanpa_mfa).
 *   docker exec ... php artisan migrate:fresh --seeder=PanduanSeeder
 */
class PanduanSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('PanduanSeeder hanya boleh dijalankan di APP_ENV=local (data demo tidak boleh ada di staging/produksi).');
        }

        $sandi = (string) config('panduan.sandi');

        if (strlen($sandi) < 12) {
            throw new RuntimeException('Set PANDUAN_PASSWORD (min. 12 karakter) di lingkungan sebelum menjalankan PanduanSeeder.');
        }

        (new DataUat('panduan', 'contoh.test', fn () => $sandi))->siapkan();

        $admin = User::firstOrNew(['email' => 'panduan.super-admin@contoh.test']);
        $admin->forceFill(['name' => 'Panduan Super-admin', 'password' => $sandi, 'aktif' => true, 'email_verified_at' => now()])->save();
        $admin->syncRoles(['super-admin']);
    }
}
