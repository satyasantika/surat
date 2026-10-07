<?php

namespace App\Filament\Resources\RubrikLpjs;

use App\Filament\Resources\RubrikLpjs\Pages\ManageRubrikLpjs;
use App\Models\Jabatan;
use App\Models\RubrikLpj;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RubrikLpjResource extends Resource
{
    protected static ?string $model = RubrikLpj::class;

    protected static ?string $slug = 'rubrik-lpj';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $modelLabel = 'rubrik LPJ';

    protected static ?string $pluralModelLabel = 'rubrik LPJ';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(150),
            TextInput::make('nilai_maks')->label('Nilai maksimum')->numeric()->integer()->minValue(1)->maxValue(100)->required(),
            CheckboxList::make('penilai_jabatan')->label('Jabatan penilai')->required()->minItems(1)
                ->options(fn () => Jabatan::orderBy('urutan')->pluck('nama', 'kode'))->columns(2),
            TextInput::make('urutan')->numeric()->integer()->minValue(0)->default(0),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('urutan'),
                TextColumn::make('kode'),
                TextColumn::make('nama'),
                TextColumn::make('nilai_maks')->label('Maks'),
                TextColumn::make('penilai_jabatan')->label('Penilai')->badge(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRubrikLpjs::route('/')];
    }
}
