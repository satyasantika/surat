<?php

namespace App\Filament\Resources\Galeris;

use App\Filament\Resources\Galeris\Pages\ManageGaleris;
use App\Models\Galeri;
use App\Models\Ormawa;
use App\Rules\TautanGaleriValid;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GaleriResource extends Resource
{
    protected static ?string $model = Galeri::class;

    protected static ?string $slug = 'galeri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'item galeri';

    protected static ?string $pluralModelLabel = 'galeri';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')->required()->maxLength(200),
            Select::make('tipe')->options(Galeri::TIPE)->required()->live(),
            TextInput::make('url')->label('Tautan')->required()->url()->maxLength(2048)
                ->rules(fn ($get) => [new TautanGaleriValid($get('tipe'))]),
            Select::make('ormawa_id')->label('Ormawa')->options(fn () => Ormawa::orderBy('nama')->pluck('nama', 'id'))->searchable()->nullable(),
            TextInput::make('urutan')->numeric()->integer()->minValue(0)->default(0),
            Toggle::make('aktif')->label('Tampil di galeri publik'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('ormawa'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('judul')->searchable()->wrap(),
                TextColumn::make('tipe')->badge()->formatStateUsing(fn (string $state) => Galeri::TIPE[$state] ?? $state),
                TextColumn::make('ormawa.nama')->label('Ormawa')->placeholder('Fakultas'),
                IconColumn::make('aktif')->boolean(),
                TextColumn::make('urutan')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipe')->options(Galeri::TIPE),
                TernaryFilter::make('aktif'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGaleris::route('/')];
    }
}
