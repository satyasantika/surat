<?php

namespace App\Filament\Resources\RuanganLokals;

use App\Exceptions\RuanganBentrok;
use App\Models\PemakaianRuanganLokal;
use App\Models\RuanganLokal;
use App\Services\Ruangan\LayananRuanganLokal;
use App\Support\SesiRuangan;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Pemakaian manual non-ormawa (pengganti booking manual lama); bentrok dicek dengan sesi bertumpuk. */
class PemakaianRelationManager extends RelationManager
{
    protected static string $relationship = 'pemakaian';

    protected static ?string $title = 'Pemakaian';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal')->required(),
            Select::make('sesi')->options(fn () => array_combine(SesiRuangan::kode(), SesiRuangan::kode()))->required(),
            TextInput::make('keterangan')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable(),
                TextColumn::make('sesi')->badge(),
                TextColumn::make('keterangan')->placeholder('—'),
                TextColumn::make('permohonan_id')->label('Permohonan')->formatStateUsing(fn (?string $state) => $state ? 'ya' : 'manual'),
            ])
            ->headerActions([
                CreateAction::make()->using(function (array $data, RelationManager $livewire, CreateAction $action): Model {
                    /** @var RuanganLokal $ruangan */
                    $ruangan = $livewire->getOwnerRecord();

                    try {
                        $id = (new LayananRuanganLokal)->catatPemakaian($ruangan->kode, $data['tanggal'], $data['sesi'], null, $data['keterangan'] ?? null);
                    } catch (RuanganBentrok $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        $action->halt();

                        throw $e;
                    }

                    return PemakaianRuanganLokal::findOrFail($id);
                }),
            ])
            ->recordActions([DeleteAction::make()->visible(fn (PemakaianRuanganLokal $record) => $record->permohonan_id === null)]);
    }
}
