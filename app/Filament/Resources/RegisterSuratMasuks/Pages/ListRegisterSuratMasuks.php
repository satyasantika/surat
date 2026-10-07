<?php

namespace App\Filament\Resources\RegisterSuratMasuks\Pages;

use App\Filament\Resources\RegisterSuratMasuks\RegisterSuratMasukResource;
use App\Models\SuratMasuk;
use App\Services\Register\EksporXlsx;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\URL;

class ListRegisterSuratMasuks extends ListRecords
{
    protected static string $resource = RegisterSuratMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor')->label('Ekspor XLSX')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => RegisterSuratMasukResource::canViewAny())
                ->action(function () {
                    abort_unless(RegisterSuratMasukResource::canViewAny(), 403);
                    $pengguna = auth()->user();

                    $nama = app(EksporXlsx::class)->tulis(
                        $this->getFilteredSortedTableQuery(),
                        ['No. agenda', 'Tanggal terima', 'No. surat', 'Tanggal surat', 'Asal', 'Perihal', 'Keamanan', 'Kecepatan', 'Status'],
                        fn (SuratMasuk $s) => [
                            $s->nomor_agenda, $s->tanggal_terima->toDateString(), $s->nomor_surat, $s->tanggal_surat->toDateString(), $s->asal,
                            $s->perihalUntuk($pengguna), $s->klasifikasi_keamanan->label(), $s->derajat_kecepatan->label(), $s->status->label(),
                        ],
                        $pengguna,
                        'register-masuk',
                    );

                    Notification::make()->title('Ekspor siap')->body('Berkas dihapus otomatis dalam 24 jam.')
                        ->actions([Action::make('unduh')->label('Unduh')
                            ->url(URL::temporarySignedRoute('ekspor.unduh', now()->addMinutes(30), ['berkas' => $nama]), shouldOpenInNewTab: true)])
                        ->success()->send();
                }),
        ];
    }
}
