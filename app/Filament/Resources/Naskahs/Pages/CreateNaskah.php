<?php

namespace App\Filament\Resources\Naskahs\Pages;

use App\Actions\Naskah\SimpanDraf;
use App\Filament\Resources\Naskahs\NaskahResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateNaskah extends CreateRecord
{
    protected static string $resource = NaskahResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SimpanDraf::class)->jalankan(null, $data, auth()->user());
    }
}
