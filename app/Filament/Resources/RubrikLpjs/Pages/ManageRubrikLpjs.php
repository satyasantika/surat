<?php

namespace App\Filament\Resources\RubrikLpjs\Pages;

use App\Filament\Resources\RubrikLpjs\RubrikLpjResource;
use App\Models\RubrikLpj;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRubrikLpjs extends ManageRecords
{
    protected static string $resource = RubrikLpjResource::class;

    public function getSubheading(): ?string
    {
        return 'Total nilai maksimum rubrik aktif: '.RubrikLpj::totalMaks();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
