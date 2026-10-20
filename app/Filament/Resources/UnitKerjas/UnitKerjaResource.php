<?php

namespace App\Filament\Resources\UnitKerjas;

use App\Enums\TipeUnitKerja;
use App\Filament\Resources\UnitKerjas\Pages\ManageUnitKerjas;
use App\Models\UnitKerja;
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
use Filament\Tables\Table;

class UnitKerjaResource extends Resource
{
    protected static ?string $model = UnitKerja::class;

    protected static ?string $slug = 'unit-kerja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'unit kerja';

    protected static ?string $pluralModelLabel = 'unit kerja';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(255),
            Select::make('tipe')->label('Tipe unit')->options(collect(TipeUnitKerja::cases())->mapWithKeys(fn (TipeUnitKerja $t) => [$t->value => $t->label()]))->required()->default('lainnya')
                ->helperText('Program studi dan laboratorium dapat mengajukan permohonan sendiri.'),
            Select::make('induk_id')->label('Unit induk')
                ->relationship('induk', 'nama')->searchable()->preload(),
            Toggle::make('aktif')->default(true),
            Select::make('pengaju')->label('Pengaju permohonan')->relationship('pengaju', 'name')->multiple()->preload()->searchable()
                ->visible(fn (?UnitKerja $record) => $record !== null)
                ->helperText('Akun berperan pengaju-unit yang mengajukan permohonan atas nama unit ini (khusus program studi/laboratorium).'),
            Select::make('penyetuju')->label('Kepala unit (penyetuju)')->relationship('penyetuju', 'name')->multiple()->preload()->searchable()
                ->visible(fn (?UnitKerja $record) => $record !== null)
                ->helperText('Akun berperan kaprodi/kalab yang menyetujui permohonan unit ini sebelum masuk ke fakultas.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('tipe')->badge()->formatStateUsing(fn ($state) => $state instanceof TipeUnitKerja ? $state->label() : $state),
                TextColumn::make('induk.nama')->label('Induk')->placeholder('—'),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnitKerjas::route('/')];
    }
}
