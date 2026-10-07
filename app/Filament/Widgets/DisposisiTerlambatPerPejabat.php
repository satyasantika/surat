<?php

namespace App\Filament\Widgets;

use App\Enums\StatusDisposisiPenerima;
use App\Models\DisposisiPenerima;
use Filament\Widgets\Widget;

/** Peringkat pejabat dengan disposisi terlambat/belum selesai terbanyak (nama dan jabatan saja). */
class DisposisiTerlambatPerPejabat extends Widget
{
    protected static ?int $sort = 15;

    protected string $view = 'filament.widgets.disposisi-terlambat-per-pejabat';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('laporan.lihat');
    }

    /** @return list<array{nama: string, jabatan: string, terlambat: int}> */
    public function baris(): array
    {
        return DisposisiPenerima::where('status', '!=', StatusDisposisiPenerima::Selesai->value)
            ->where(fn ($q) => $q->where('terlambat', true)->orWhereHas('disposisi', fn ($d) => $d->where('batas_waktu', '<', now())))
            ->with(['user:id,name', 'jabatan:id,nama'])->get()
            ->groupBy('user_id')
            ->map(fn ($k) => ['nama' => $k->first()->user->name, 'jabatan' => (string) $k->pluck('jabatan.nama')->filter()->unique()->implode('; '), 'terlambat' => $k->count()])
            ->sortByDesc('terlambat')->take(8)->values()->all();
    }
}
