<?php

namespace App\Filament\Resources\SuratMasuks\Pages;

use App\Filament\Resources\SuratMasuks\SuratMasukResource;
use App\Models\SuratMasuk;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSuratMasuk extends EditRecord
{
    protected static string $resource = SuratMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuratMasukResource::aksiTerima()->record($this->record)->after(function () {
                $this->record->refresh();
                $this->refreshFormData(['nomor_agenda', 'tanggal_terima']);
            }),
            // Kiriman yang belum diterima belum bernomor agenda: lembar disposisi belum tersedia.
            Action::make('lembar')->label('Cetak lembar disposisi')->icon('heroicon-o-printer')
                ->visible(fn () => $this->record instanceof SuratMasuk && ! $this->record->masihDiajukan())
                ->url(fn () => route('surat-masuk.lembar-disposisi', $this->record), shouldOpenInNewTab: true),
        ];
    }
}
