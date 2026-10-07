<?php

namespace App\Filament\Resources\Naskahs\Pages;

use App\Filament\Resources\Naskahs\NaskahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNaskahs extends ListRecords
{
    protected static string $resource = NaskahResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat draf naskah')];
    }
}
