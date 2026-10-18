<?php

namespace App\Filament\Resources\UnitPembuats;

use App\Filament\Resources\UnitPembuats\Pages\ManageUnitPembuats;
use App\Models\UnitPembuat;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Master unit pembuat surat (BR-26): dikelola super-admin lewat izin master.kelola; tanpa data baku yang dikunci. */
class UnitPembuatResource extends Resource
{
    protected static ?string $model = UnitPembuat::class;

    protected static ?string $slug = 'unit-pembuat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'unit pembuat surat';

    protected static ?string $pluralModelLabel = 'unit pembuat surat';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(150),
            Textarea::make('deskripsi')->maxLength(2000)->columnSpanFull(),
            Select::make('petugas')->label('Petugas unit')->relationship('petugas', 'name')->multiple()->preload()->searchable()
                ->helperText('Penerima penugasan dari kasubag. Untuk membuat draf surat izin, petugas perlu izin naskah.draf.')
                ->columnSpanFull(),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->modifyQueryUsing(fn ($query) => $query->withCount('petugas'))
            ->columns([
                TextColumn::make('kode')->searchable(),
                TextColumn::make('nama')->searchable(),
                TextColumn::make('petugas_count')->label('Petugas'),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                // Unit yang pernah dipakai permohonan tidak dihapus (jejak); nonaktifkan saja.
                DeleteAction::make()->hidden(fn (UnitPembuat $record) => $record->permohonan()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnitPembuats::route('/')];
    }
}
