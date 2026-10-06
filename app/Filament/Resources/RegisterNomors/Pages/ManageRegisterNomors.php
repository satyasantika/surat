<?php

namespace App\Filament\Resources\RegisterNomors\Pages;

use App\Filament\Resources\RegisterNomors\RegisterNomorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegisterNomors extends ManageRecords
{
    protected static string $resource = RegisterNomorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
