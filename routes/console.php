<?php

use Illuminate\Support\Facades\Schedule;

// Berkas sementara (ekspor) dihapus otomatis paling lambat 24 jam (STANDAR-TEKNIS §1a.6).
Schedule::command('surat:bersihkan-tmp')->hourly()->withoutOverlapping()->onOneServer();

// Jadwal 02-ARSITEKTUR §8. Semua tanpa tumpang tindih dan hanya satu server yang menjalankan.
Schedule::command('surat:pengingat-disposisi')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('surat:pengingat-permohonan')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('surat:pengingat-lpj')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('surat:tandai-blokir-lpj')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('surat:laporan-retensi')->monthlyOn(1, '06:30')->withoutOverlapping()->onOneServer();
Schedule::command('surat:periksa-tautan')->weeklyOn(1, '06:00')->withoutOverlapping()->onOneServer();

// Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon/Redis. Penjadwal menyalakan worker tiap menit dan
// berhenti saat antrean kosong; jeda proses job paling lama ±1 menit.
// --memory=256 pada worker default melindungi RAM server (3,8 GiB) dari job render PDF.
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=55 --timeout=60 --tries=3 --memory=256')
    ->everyMinute()->withoutOverlapping(2)->runInBackground();

Schedule::command('queue:work database-berat --queue=berat --stop-when-empty --max-time=600 --timeout=900 --tries=2 --memory=512')
    ->everyMinute()->withoutOverlapping(30)->runInBackground();

// Detak penjadwal: dibaca /api/health; peringatan bila lebih tua dari 5 menit.
Schedule::call(fn () => cache()->put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600))
    ->everyMinute()->name('detak-penjadwal');

Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:00');
Schedule::command('queue:prune-batches --hours=168')->dailyAt('03:05');
