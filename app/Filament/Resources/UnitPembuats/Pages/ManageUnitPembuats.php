<?php

namespace App\Filament\Resources\UnitPembuats\Pages;

use App\Filament\Resources\UnitPembuats\UnitPembuatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUnitPembuats extends ManageRecords
{
    protected static string $resource = UnitPembuatResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
