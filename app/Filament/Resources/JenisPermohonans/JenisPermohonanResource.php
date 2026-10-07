<?php

namespace App\Filament\Resources\JenisPermohonans;

use App\Filament\Resources\JenisPermohonans\Pages\ManageJenisPermohonans;
use App\Models\JenisNaskah;
use App\Models\JenisPermohonan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JenisPermohonanResource extends Resource
{
    protected static ?string $model = JenisPermohonan::class;

    protected static ?string $slug = 'jenis-permohonan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'jenis permohonan';

    protected static ?string $pluralModelLabel = 'jenis permohonan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(150),
            Toggle::make('butuh_ruangan')->label('Butuh ruangan FKIP'),
            Toggle::make('butuh_fasilitas_rektorat')->label('Butuh fasilitas rektorat'),
            Select::make('jenis_naskah_id')->label('Templat surat izin/pengantar')
                ->options(fn () => JenisNaskah::where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))->searchable(),
            CheckboxList::make('berkas_wajib')->label('Berkas wajib (tautan)')
                ->options(array_combine(config('berkas.jenis'), config('berkas.jenis')))->columns(3),
            Toggle::make('aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('kode')->searchable(),
                TextColumn::make('nama')->searchable(),
                IconColumn::make('butuh_ruangan')->label('Ruangan')->boolean(),
                IconColumn::make('butuh_fasilitas_rektorat')->label('Rektorat')->boolean(),
                TextColumn::make('berkas_wajib')->label('Berkas wajib')->badge(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageJenisPermohonans::route('/')];
    }
}
