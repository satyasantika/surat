<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Pengguna\SimpanPengguna;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->record;
        $data['peran'] = $user->roles->pluck('name')->all();
        $data['izin_langsung'] = $user->permissions->pluck('name')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        return app(SimpanPengguna::class)->jalankan($record, $data, auth()->user());
    }
}
