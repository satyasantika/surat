<?php

namespace App\Filament\Resources\SuratMasuks\Pages;

use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Filament\Resources\SuratMasuks\SuratMasukResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSuratMasuk extends CreateRecord
{
    protected static string $resource = SuratMasukResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(RegistrasiSuratMasuk::class)->jalankan($data, auth()->user());
    }
}
