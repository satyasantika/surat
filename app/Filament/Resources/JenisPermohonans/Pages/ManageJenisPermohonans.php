<?php

namespace App\Filament\Resources\JenisPermohonans\Pages;

use App\Filament\Resources\JenisPermohonans\JenisPermohonanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJenisPermohonans extends ManageRecords
{
    protected static string $resource = JenisPermohonanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
