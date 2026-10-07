<?php

namespace App\Filament\Resources\RegisterSuratKeluars\Pages;

use App\Filament\Resources\RegisterSuratKeluars\RegisterSuratKeluarResource;
use App\Models\Naskah;
use App\Services\Register\EksporXlsx;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\URL;

class ListRegisterSuratKeluars extends ListRecords
{
    protected static string $resource = RegisterSuratKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor')->label('Ekspor XLSX')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => RegisterSuratKeluarResource::canViewAny())
                ->action(function () {
                    abort_unless(RegisterSuratKeluarResource::canViewAny(), 403);

                    $nama = app(EksporXlsx::class)->tulis(
                        $this->getFilteredSortedTableQuery()->with(['jenis', 'penandaTanganUser', 'tujuan']),
                        ['Nomor', 'Tanggal', 'Jenis', 'Perihal', 'Tujuan', 'Penanda tangan', 'Status'],
                        fn (Naskah $n) => [
                            $n->nomor, $n->tanggal_naskah?->toDateString(), $n->jenis->nama, RegisterSuratKeluarResource::perihalUntuk($n),
                            $n->tujuan->pluck('nama')->implode('; '), $n->penandaTanganUser?->name, $n->status->label(),
                        ],
                        auth()->user(),
                        'register-keluar',
                    );

                    Notification::make()->title('Ekspor siap')->body('Berkas dihapus otomatis dalam 24 jam.')
                        ->actions([Action::make('unduh')->label('Unduh')
                            ->url(URL::temporarySignedRoute('ekspor.unduh', now()->addMinutes(30), ['berkas' => $nama]), shouldOpenInNewTab: true)])
                        ->success()->send();
                }),
        ];
    }
}
