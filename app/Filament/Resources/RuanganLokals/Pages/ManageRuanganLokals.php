<?php

namespace App\Filament\Resources\RuanganLokals\Pages;

use App\Filament\Resources\RuanganLokals\RuanganLokalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRuanganLokals extends ManageRecords
{
    protected static string $resource = RuanganLokalResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
