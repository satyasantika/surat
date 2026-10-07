<?php

namespace App\Filament\Resources\Naskahs\Pages;

use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\SimpanDraf;
use App\Filament\Resources\Naskahs\NaskahResource;
use App\Models\Naskah;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNaskah extends EditRecord
{
    protected static string $resource = NaskahResource::class;

    private function naskah(): Naskah
    {
        /** @var Naskah $naskah */
        $naskah = $this->record;

        return $naskah;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajukanParaf')->label('Ajukan paraf')->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->naskah()->status->value === 'draf' && $this->naskah()->penyusun_id === auth()->id())
                ->schema([
                    Repeater::make('pemaraf')->label('Pemaraf (berurutan, boleh kosong)')->default([])->simple(
                        Select::make('user_id')->options(fn () => User::where('aktif', true)->orderBy('name')->get()
                            ->filter(fn (User $u) => $u->can('naskah.paraf'))->pluck('name', 'id'))->required()->searchable(),
                    ),
                ])
                ->action(function (array $data) {
                    app(AjukanParaf::class)->jalankan($this->naskah(), auth()->user(), array_values($data['pemaraf'] ?? []));
                    Notification::make()->title('Naskah diajukan')->success()->send();
                    $this->redirect(NaskahResource::getUrl('index'));
                }),
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
