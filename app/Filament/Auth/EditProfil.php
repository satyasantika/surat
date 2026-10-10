<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Halaman profil Persuratan FKIP: mengganti kata sandi mencabut
 * wajib_ganti_sandi dan mengeluarkan sesi di perangkat lain.
 */
class EditProfil extends EditProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $kataSandiBaru = $this->data['password'] ?? null;

        if (filled($kataSandiBaru)) {
            $data['wajib_ganti_sandi'] = false;
        }

        $record = parent::handleRecordUpdate($record, $data);

        if (filled($kataSandiBaru)) {
            /** @var SessionGuard $guard */
            $guard = Filament::auth();
            $guard->logoutOtherDevices($kataSandiBaru);
        }

        return $record;
    }
}
