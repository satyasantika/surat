<?php

namespace App\Filament\Resources\JenisNaskahs\Pages;

use App\Filament\Resources\JenisNaskahs\JenisNaskahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJenisNaskahs extends ManageRecords
{
    protected static string $resource = JenisNaskahResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
