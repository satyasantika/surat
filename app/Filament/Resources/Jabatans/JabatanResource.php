<?php

namespace App\Filament\Resources\Jabatans;

use App\Filament\Resources\Jabatans\Pages\ManageJabatans;
use App\Filament\Resources\Jabatans\RelationManagers\PemangkuRelationManager;
use App\Models\Jabatan;
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

class JabatanResource extends Resource
{
    protected static ?string $model = Jabatan::class;

    protected static ?string $slug = 'jabatan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $modelLabel = 'jabatan';

    protected static ?string $pluralModelLabel = 'jabatan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public const BIDANG = ['akademik' => 'Akademik', 'umum_keuangan' => 'Umum dan Keuangan', 'kemahasiswaan' => 'Kemahasiswaan'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(150)->helperText('Dipakai pada naskah.'),
            Select::make('unit_kerja_id')->label('Unit kerja')->relationship('unitKerja', 'nama')->required()->searchable()->preload(),
            Select::make('bidang')->options(self::BIDANG)->placeholder('Tanpa bidang'),
            Toggle::make('dapat_menandatangani')->label('Dapat menandatangani'),
            TextInput::make('urutan')->numeric()->default(0)->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('urutan')->sortable(),
                TextColumn::make('kode')->searchable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('bidang')->badge()->formatStateUsing(fn (?string $state) => self::BIDANG[$state] ?? $state),
                IconColumn::make('dapat_menandatangani')->label('TTD')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PemangkuRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageJabatans::route('/')];
    }
}
