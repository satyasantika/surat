<?php

namespace App\Filament\Resources\Galeris\Pages;

use App\Actions\Galeri\SarankanGaleriDariLpj;
use App\Filament\Resources\Galeris\GaleriResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageGaleris extends ManageRecords
{
    protected static string $resource = GaleriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saran')->label('Ambil saran dari LPJ')->color('gray')
                ->modalDescription('Menyalin tautan Instagram/video dari LPJ yang sudah dinilai sebagai item nonaktif; aktifkan yang layak tampil.')
                ->requiresConfirmation()
                ->action(function () {
                    $h = app(SarankanGaleriDariLpj::class)->jalankan(auth()->user());
                    Notification::make()->title("{$h['dibuat']} saran ditambahkan, {$h['dilewati']} dilewati")->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
