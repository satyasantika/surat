<?php

namespace App\Console\Commands;

use App\Actions\Migrasi\ImporOrmawaHub;
use App\Exceptions\PemetaanTidakLengkap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ImporOrmawaHubCommand extends Command
{
    protected $signature = 'ormawahub:impor {xlsx : Berkas XLSX ekspor OrmawaHub} {--pemetaan= : Direktori CSV pemetaan} {--dry-run : Jalankan lalu rollback}';

    protected $description = 'Mengimpor data OrmawaHub (XLSX + pemetaan CSV); XLSX dan CSV dihapus setelah impor sungguhan tanpa galat';

    public function handle(ImporOrmawaHub $impor): int
    {
        $pemetaan = (string) $this->option('pemetaan');

        if ($pemetaan === '' || ! is_dir($pemetaan)) {
            $this->components->error('Opsi --pemetaan wajib menunjuk direktori berisi CSV pemetaan.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        try {
            $k = $impor->jalankan((string) $this->argument('xlsx'), $pemetaan, $dry);
        } catch (PemetaanTidakLengkap $e) {
            $this->components->error('Pemetaan tidak lengkap; tidak ada data ditulis:');

            foreach ($e->galat as $g) {
                $this->line(" - {$g}");
            }

            return self::FAILURE;
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $baris = [];
        $galat = 0;

        foreach ($k->hitung as $sheet => $h) {
            $galat += $h['galat'] ?? 0;
            $baris[] = [$sheet, $h['ok'] ?? 0, $h['peringatan'] ?? 0, $h['lewati'] ?? 0, $h['galat'] ?? 0];
        }

        $this->table(['Sheet', 'ok', 'peringatan', 'lewati', 'galat'], $baris);

        foreach (array_filter($k->catatan, fn ($c) => in_array($c['status'], ['galat', 'peringatan'], true)) as $c) {
            $this->line(sprintf('[%s] %s %s: %s', $c['status'], $c['sheet'], $c['id'], $c['pesan']));
        }

        $this->components->info(($dry ? 'DRY-RUN selesai (dibatalkan)' : 'Impor selesai').". Batch {$k->batch}.");

        if (! $dry && $galat === 0) {
            File::delete((string) $this->argument('xlsx'));
            File::delete(glob(rtrim($pemetaan, '/').'/*.csv') ?: []);
            $this->components->info('XLSX dan CSV pemetaan dihapus (berisi data pribadi).');
        }

        return $galat > 0 ? self::FAILURE : self::SUCCESS;
    }
}
