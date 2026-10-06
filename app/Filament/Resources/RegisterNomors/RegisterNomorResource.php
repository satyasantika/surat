<?php

namespace App\Filament\Resources\RegisterNomors;

use App\Filament\Resources\RegisterNomors\Pages\ManageRegisterNomors;
use App\Models\RegisterNomor;
use App\Rules\PolaNomorSah;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegisterNomorResource extends Resource
{
    protected static ?string $model = RegisterNomor::class;

    protected static ?string $slug = 'register-nomor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?string $modelLabel = 'register nomor';

    protected static ?string $pluralModelLabel = 'register nomor';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(30)->unique(ignoreRecord: true)->alphaDash(),
            TextInput::make('nama')->required()->maxLength(150),
            TextInput::make('pola')->required()->maxLength(150)
                ->helperText('Token: {urut}, {urut:3}, {kode_unit}, {klasifikasi}, {bulan_romawi}, {tahun}, {kode_jenis}. Hanya berlaku untuk nomor berikutnya.')
                ->rule(new PolaNomorSah),
            Select::make('reset')->options(RegisterNomor::RESET)->required()->default('tahunan'),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->searchable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('pola')->fontFamily('mono'),
                TextColumn::make('reset')->badge()->formatStateUsing(fn (string $state) => RegisterNomor::RESET[$state] ?? $state),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRegisterNomors::route('/')];
    }
}
