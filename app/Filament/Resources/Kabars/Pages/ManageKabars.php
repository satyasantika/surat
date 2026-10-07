<?php

namespace App\Filament\Resources\Kabars\Pages;

use App\Actions\Kabar\SimpanKabar;
use App\Filament\Resources\Kabars\KabarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageKabars extends ManageRecords
{
    protected static string $resource = KabarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): Model => app(SimpanKabar::class)->jalankan(auth()->user(), $data)),
        ];
    }
}
