<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPermohonan;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Support\Pengaturan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dasbor Layanan Ormawa: permohonan per tahap dan LPJ terlambat (angka agregat saja). */
class RingkasanLayananOrmawa extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected ?string $heading = 'Layanan Ormawa';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u !== null && $u->canAny(['laporan.lihat', 'permohonan.validasi', 'permohonan.putuskan']);
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $per = Permohonan::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $hitung = fn (StatusPermohonan ...$status) => (int) collect($status)->sum(fn (StatusPermohonan $s) => $per[$s->value] ?? 0);
        $lpjTerlambat = Lpj::where('status', Lpj::DRAF)->whereDate('batas_waktu', '<', now()->startOfDay()->subDays((int) Pengaturan::get('toleransi_lpj_hari'))->toDateString())->count();

        return [
            Stat::make('Menunggu validasi', $hitung(StatusPermohonan::Diajukan, StatusPermohonan::PersetujuanPembina, StatusPermohonan::ValidasiAdmin)),
            Stat::make('Menunggu disposisi Dekan', $hitung(StatusPermohonan::DisposisiDekan)),
            Stat::make('Menunggu keputusan WD', $hitung(StatusPermohonan::PersetujuanWd)),
            Stat::make('Menunggu rekomendasi Kasubag', $hitung(StatusPermohonan::RekomendasiKasubag)),
            Stat::make('Menunggu penerbitan surat', $hitung(StatusPermohonan::Penerbitan)),
            Stat::make('Dikembalikan ke ormawa', $hitung(StatusPermohonan::Dikembalikan)),
            Stat::make('LPJ terlambat', $lpjTerlambat)->color($lpjTerlambat > 0 ? 'danger' : 'success'),
            Stat::make('Ormawa terblokir (LPJ)', Ormawa::where('diblokir_lpj', true)->count())->color('warning'),
        ];
    }
}
