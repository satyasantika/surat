<?php

namespace App\Console\Commands;

use App\Support\Uat\DataUat;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SiapkanUat extends Command
{
    protected $signature = 'surat:siapkan-uat {--domain=contoh.test : Domain surel akun rekaan} {--awalan=uat : Awalan surel akun rekaan}';

    protected $description = 'Menyiapkan data REKAAN uji penerimaan (staging): akun per peran, 3 ormawa, surat masuk, permohonan di tiap tahap, LPJ, kabar, galeri';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('Perintah ini hanya untuk staging/lokal dan ditolak di produksi (data rekaan tidak boleh bercampur dengan data nyata).');

            return self::FAILURE;
        }

        $hasil = (new DataUat((string) $this->option('awalan'), (string) $this->option('domain'), fn () => Str::password(16, symbols: false)))->siapkan();

        $this->table(['Surel', 'Peran', 'Kata sandi'], array_map(fn ($a) => [$a['surel'], $a['peran'], $a['sandi'] ?? '(akun sudah ada; tidak diubah)'], $hasil['akun']));
        $this->components->info('Ringkasan: '.collect($hasil['ringkasan'])->map(fn ($n, $k) => "{$k} {$n}")->implode(', ').'.');
        $this->components->warn('Catat kata sandi di atas sekarang (tidak disimpan di mana pun). Pejabat/admin wajib menyiapkan MFA saat masuk pertama ke /admin.');

        return self::SUCCESS;
    }
}
