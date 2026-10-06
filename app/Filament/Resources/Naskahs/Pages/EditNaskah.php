<?php

namespace App\Filament\Resources\Naskahs\Pages;

use App\Actions\Naskah\SimpanDraf;
use App\Filament\Resources\Naskahs\NaskahResource;
use App\Models\Naskah;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNaskah extends EditRecord
{
    protected static string $resource = NaskahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pratinjau')->label('Pratinjau')->icon('heroicon-o-eye')
                ->url(fn () => route('naskah.pratinjau', $this->record), shouldOpenInNewTab: true),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Naskah $naskah */
        $naskah = $this->record;

        $data['tujuan'] = $naskah->tujuan->map(fn ($t) => ['nama' => $t->nama, 'user_id' => $t->user_id])->all();
        $data['tembusan'] = $naskah->tembusan->map(fn ($t) => ['nama' => $t->nama, 'user_id' => $t->user_id])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Naskah $record */
        $data['jenis_naskah_id'] = $record->jenis_naskah_id;

        return app(SimpanDraf::class)->jalankan($record, $data, auth()->user());
    }
}
