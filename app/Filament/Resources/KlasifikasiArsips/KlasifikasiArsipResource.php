<?php

namespace App\Filament\Resources\KlasifikasiArsips;

use App\Filament\Resources\KlasifikasiArsips\Pages\ManageKlasifikasiArsips;
use App\Models\KlasifikasiArsip;
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

class KlasifikasiArsipResource extends Resource
{
    protected static ?string $model = KlasifikasiArsip::class;

    protected static ?string $slug = 'klasifikasi-arsip';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'klasifikasi arsip';

    protected static ?string $pluralModelLabel = 'klasifikasi arsip';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(255),
            Select::make('induk_id')->label('Induk')
                ->relationship('induk', 'nama', fn ($query, ?KlasifikasiArsip $record) => $record ? $query->whereKeyNot($record->getKey()) : $query)
                ->searchable()->preload(),
            TextInput::make('retensi_aktif_tahun')->label('Retensi aktif (tahun)')->numeric()->minValue(0)->maxValue(100),
            TextInput::make('retensi_inaktif_tahun')->label('Retensi inaktif (tahun)')->numeric()->minValue(0)->maxValue(100),
            Select::make('keterangan_akhir')->label('Keterangan akhir')->options(KlasifikasiArsip::KETERANGAN_AKHIR),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->searchable()->sortable(),
                TextColumn::make('nama')->searchable()->wrap(),
                TextColumn::make('induk.kode')->label('Induk')->placeholder('—'),
                TextColumn::make('retensi_aktif_tahun')->label('Aktif (th)')->placeholder('—'),
                TextColumn::make('retensi_inaktif_tahun')->label('Inaktif (th)')->placeholder('—'),
                TextColumn::make('keterangan_akhir')->label('Akhir')->badge()->formatStateUsing(fn (?string $state) => KlasifikasiArsip::KETERANGAN_AKHIR[$state] ?? $state),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageKlasifikasiArsips::route('/')];
    }
}
