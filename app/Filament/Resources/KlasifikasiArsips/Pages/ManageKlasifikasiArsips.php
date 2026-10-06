<?php

namespace App\Filament\Resources\KlasifikasiArsips\Pages;

use App\Filament\Imports\KlasifikasiArsipImporter;
use App\Filament\Resources\KlasifikasiArsips\KlasifikasiArsipResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKlasifikasiArsips extends ManageRecords
{
    protected static string $resource = KlasifikasiArsipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()->importer(KlasifikasiArsipImporter::class)->label('Impor CSV'),
            CreateAction::make(),
        ];
    }
}
