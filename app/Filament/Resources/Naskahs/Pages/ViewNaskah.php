<?php

namespace App\Filament\Resources\Naskahs\Pages;

use App\Actions\Naskah\BatalkanNaskah;
use App\Contracts\PenyimpananBerkas;
use App\Filament\Resources\Naskahs\NaskahResource;
use App\Models\Naskah;
use App\Rules\TautanBerkasValid;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewNaskah extends ViewRecord
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
            EditAction::make(),
            Action::make('pdf')->label('Unduh PDF')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => $this->naskah()->snapshot !== null && $this->naskah()->status->value !== 'dibatalkan')
                ->url(fn () => route('naskah.pdf', $this->naskah()), shouldOpenInNewTab: true),
            Action::make('pratinjau')->label('Pratinjau')->icon('heroicon-o-eye')
                ->url(fn () => route('naskah.pratinjau', $this->naskah()), shouldOpenInNewTab: true),
            Action::make('batalkan')->label('Batalkan naskah')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => auth()->user()->can('batalkan', $this->naskah()))
                ->requiresConfirmation()->modalDescription('Naskah tetap tercatat di register dengan status dibatalkan; nomornya tidak dipakai ulang.')
                ->schema([Textarea::make('alasan')->required()->minLength(5)->maxLength(2000)])
                ->action(function (array $data) {
                    app(BatalkanNaskah::class)->jalankan($this->naskah(), auth()->user(), $data['alasan']);
                    Notification::make()->title('Naskah dibatalkan')->success()->send();
                    $this->redirect(NaskahResource::getUrl('view', ['record' => $this->naskah()]));
                }),
            Action::make('catatPindaian')->label('Catat pindaian bertanda tangan')->icon('heroicon-o-paper-clip')
                ->visible(fn () => auth()->user()->can('catatPindaian', $this->naskah()))
                ->schema([
                    TextInput::make('url')->label('Tautan pindaian')->url()->required()->maxLength(2048)->rule(new TautanBerkasValid),
                ])
                ->action(function (array $data) {
                    Gate::authorize('catatPindaian', $this->naskah());
                    app(PenyimpananBerkas::class)->simpan($this->naskah(), 'naskah_basah', $data['url'], 'Pindaian naskah bertanda tangan basah', auth()->user());
                    Notification::make()->title('Pindaian dicatat')->success()->send();
                }),
        ];
    }
}
