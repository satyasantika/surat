<?php

namespace App\Filament\Resources\RuanganLokals;

use App\Filament\Resources\RuanganLokals\Pages\ManageRuanganLokals;
use App\Models\RuanganLokal;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Katalog ruangan mode lokal (sebelum integrasi Aset). */
class RuanganLokalResource extends Resource
{
    protected static ?string $model = RuanganLokal::class;

    protected static ?string $slug = 'ruangan-lokal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $modelLabel = 'ruangan';

    protected static ?string $pluralModelLabel = 'ruangan (lokal)';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(30)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(150),
            TextInput::make('gedung')->maxLength(100),
            TextInput::make('kapasitas')->numeric()->minValue(1)->maxValue(10000),
            Textarea::make('fasilitas')->rows(2)->maxLength(2000),
            Toggle::make('dalam_perawatan')->label('Dalam perawatan'),
            Toggle::make('tampil_katalog')->label('Tampil di katalog')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('kode')->searchable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('gedung')->placeholder('—'),
                TextColumn::make('kapasitas')->placeholder('—'),
                IconColumn::make('dalam_perawatan')->label('Perawatan')->boolean(),
                IconColumn::make('tampil_katalog')->label('Katalog')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PemakaianRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageRuanganLokals::route('/')];
    }
}
