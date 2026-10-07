<?php

namespace App\Filament\Resources\Lpjs\Pages;

use App\Actions\Lpj\NilaiLpj;
use App\Filament\Resources\Lpjs\LpjResource;
use App\Models\Lpj;
use App\Models\RubrikLpj;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewLpj extends ViewRecord
{
    protected static string $resource = LpjResource::class;

    private function lpj(): Lpj
    {
        /** @var Lpj $lpj */
        $lpj = $this->record;

        return $lpj;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nilai')->label('Beri nilai')->icon('heroicon-o-star')
                ->visible(fn () => auth()->user()->can('nilai', $this->lpj()))
                ->schema(function () {
                    $komponen = [];

                    foreach (NilaiLpj::rubrikUntuk(auth()->user()) as $r) {
                        /** @var RubrikLpj $r */
                        $komponen[] = TextInput::make("nilai.{$r->kode}.nilai")->label("{$r->nama} (0–{$r->nilai_maks})")->numeric()->minValue(0)->maxValue($r->nilai_maks)->required();
                        $komponen[] = Textarea::make("nilai.{$r->kode}.catatan")->label("Catatan {$r->nama}")->rows(2)->maxLength(2000);
                    }

                    return $komponen;
                })
                ->action(function (array $data) {
                    try {
                        $hasil = app(NilaiLpj::class)->jalankan($this->lpj(), auth()->user(), $data['nilai']);
                        Notification::make()->title($hasil['lengkap'] ? "Penilaian lengkap; nilai akhir {$hasil['nilai_akhir']}" : 'Nilai tersimpan; menunggu penilai lain')->success()->send();
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    }

                    $this->record->refresh();
                }),
        ];
    }
}
