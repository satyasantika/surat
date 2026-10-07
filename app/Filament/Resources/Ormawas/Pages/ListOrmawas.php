<?php

namespace App\Filament\Resources\Ormawas\Pages;

use App\Filament\Resources\Ormawas\OrmawaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrmawas extends ListRecords
{
    protected static string $resource = OrmawaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
