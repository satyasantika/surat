<?php

namespace App\Filament\Resources\JenisNaskahs;

use App\Filament\Resources\JenisNaskahs\Pages\ManageJenisNaskahs;
use App\Models\JenisNaskah;
use App\Rules\TemplatNaskahAda;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JenisNaskahResource extends Resource
{
    protected static ?string $model = JenisNaskah::class;

    protected static ?string $slug = 'jenis-naskah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'jenis naskah';

    protected static ?string $pluralModelLabel = 'jenis naskah';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('kode')->required()->maxLength(30)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('nama')->required()->maxLength(150),
            Select::make('kelompok')->options(JenisNaskah::KELOMPOK)->required(),
            Select::make('register_nomor_id')->label('Register nomor')->relationship('register', 'nama')->required()->preload(),
            TextInput::make('templat_blade')->label('Templat Blade')->required()->maxLength(100)
                ->placeholder('naskah.surat-dinas')->rule(new TemplatNaskahAda),
            TextInput::make('versi_templat')->label('Versi templat')->numeric()->minValue(1)->default(1)->required(),
            CheckboxList::make('mode_tanda_tangan_diizinkan')->label('Mode tanda tangan diizinkan')
                ->options(JenisNaskah::MODE_TANDA_TANGAN)->required()->minItems(1)->columns(3)
                ->helperText('Istilah TTE hanya untuk mode tersertifikasi (BR-09).'),
            Select::make('jabatan_penanda_tangan_bawaan_id')->label('Penanda tangan bawaan')
                ->relationship('jabatanPenandaTanganBawaan', 'nama', fn ($query) => $query->where('dapat_menandatangani', true))
                ->preload()->placeholder('Tanpa bawaan'),
            Repeater::make('variabel')->label('Variabel formulir')->columnSpanFull()->default([])
                ->schema([
                    TextInput::make('kunci')->required()->alphaDash()->maxLength(40)->distinct(),
                    TextInput::make('label')->required()->maxLength(80),
                    Select::make('tipe')->options(JenisNaskah::TIPE_VARIABEL)->required()->live(),
                    Toggle::make('wajib')->default(true),
                    KeyValue::make('opsi')->label('Pilihan')->visible(fn (Get $get) => $get('tipe') === 'select')->columnSpanFull(),
                ])->columns(4)->reorderable()->collapsible(),
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
                TextColumn::make('kelompok')->badge()->formatStateUsing(fn (string $state) => JenisNaskah::KELOMPOK[$state] ?? $state),
                TextColumn::make('register.kode')->label('Register'),
                TextColumn::make('templat_blade')->label('Templat')->fontFamily('mono'),
                TextColumn::make('versi_templat')->label('Versi'),
                IconColumn::make('aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageJenisNaskahs::route('/')];
    }
}
