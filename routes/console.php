<?php

use Illuminate\Support\Facades\Schedule;

// Berkas sementara (ekspor) dihapus otomatis paling lambat 24 jam (STANDAR-TEKNIS §1a.6).
Schedule::command('surat:bersihkan-tmp')->hourly();
