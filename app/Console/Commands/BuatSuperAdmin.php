<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class BuatSuperAdmin extends Command
{
    protected $signature = 'surat:buat-superadmin {--name= : Nama lengkap} {--email= : Surel domain unsil}';

    protected $description = 'Membuat akun super-admin (kata sandi diminta interaktif, tidak lewat argumen)';

    public function handle(): int
    {
        $nama = $this->option('name') ?: text('Nama lengkap', required: true);
        $surel = $this->option('email') ?: text('Surel (domain unsil.ac.id)', required: true);
        $sandi = password('Kata sandi (min. 12 karakter, huruf dan angka)', required: true);

        $v = Validator::make(
            ['name' => $nama, 'email' => $surel, 'password' => $sandi],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'unique:users,email', new SurelDomainUnsil],
                'password' => ['required', Password::min(12)->letters()->numbers()],
            ],
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $galat) {
                $this->components->error($galat);
            }

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => PeranDanIzinSeeder::class, '--force' => true]);

        $user = User::create(['name' => $nama, 'email' => $surel, 'password' => $sandi]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('super-admin');

        $this->components->info("Super-admin {$surel} dibuat. MFA wajib disiapkan saat masuk pertama ke /admin.");

        return self::SUCCESS;
    }
}
