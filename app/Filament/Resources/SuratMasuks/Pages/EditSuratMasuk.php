<?php

namespace App\Filament\Resources\SuratMasuks\Pages;

use App\Filament\Resources\SuratMasuks\SuratMasukResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSuratMasuk extends EditRecord
{
    protected static string $resource = SuratMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lembar')->label('Cetak lembar disposisi')->icon('heroicon-o-printer')
                ->url(fn () => route('surat-masuk.lembar-disposisi', $this->record), shouldOpenInNewTab: true),
        ];
    }
}
