<?php

use Illuminate\Support\Facades\Schedule;

// Berkas sementara (ekspor) dihapus otomatis paling lambat 24 jam (STANDAR-TEKNIS §1a.6).
Schedule::command('surat:bersihkan-tmp')->hourly()->withoutOverlapping()->onOneServer();

// Jadwal 02-ARSITEKTUR §8. Semua tanpa tumpang tindih dan hanya satu server yang menjalankan.
Schedule::command('surat:pengingat-disposisi')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('surat:pengingat-permohonan')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('surat:pengingat-lpj')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('surat:tandai-blokir-lpj')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('surat:periksa-tautan')->weeklyOn(1, '06:00')->withoutOverlapping()->onOneServer();
