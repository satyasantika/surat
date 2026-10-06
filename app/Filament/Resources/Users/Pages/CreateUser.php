<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Pengguna\SimpanPengguna;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(SimpanPengguna::class)->jalankan(null, $data, auth()->user());
    }
}
