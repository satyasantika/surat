<?php

namespace App\Filament\Widgets;

use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusNaskah;
use App\Enums\StatusSuratMasuk;
use App\Models\DisposisiPenerima;
use App\Models\Naskah;
use App\Models\SuratMasuk;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dasbor persuratan menurut peran: hanya angka agregat (tanpa data pribadi); pejabat biasa hanya melihat bagiannya. */
class RingkasanPersuratan extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $heading = 'Persuratan';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u !== null && $u->canAny(['masuk.lihat', 'naskah.tandatangan', 'naskah.draf']);
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        /** @var User $u */
        $u = auth()->user();
        $semua = $u->can('laporan.lihat');

        $terlambat = DisposisiPenerima::where('status', '!=', StatusDisposisiPenerima::Selesai->value)
            ->where(fn ($q) => $q->where('terlambat', true)->orWhereHas('disposisi', fn ($d) => $d->where('batas_waktu', '<', now())))
            ->when(! $semua, fn ($q) => $q->where('user_id', $u->getKey()))->count();

        $stats = [];

        if ($u->can('masuk.lihat')) {
            $stats[] = Stat::make('Surat masuk belum didisposisikan', SuratMasuk::where('status', StatusSuratMasuk::Diterima->value)->count());
        }

        $stats[] = Stat::make($semua ? 'Disposisi terlambat' : 'Disposisi Anda yang terlambat', $terlambat)->color($terlambat > 0 ? 'danger' : 'success');
        $stats[] = Stat::make('Naskah menunggu tanda tangan', Naskah::where('status', StatusNaskah::MenungguTandaTangan->value)->terlihatOleh($u)->count());

        return $stats;
    }
}
