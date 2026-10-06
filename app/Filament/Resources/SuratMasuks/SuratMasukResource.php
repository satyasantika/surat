<?php

namespace App\Filament\Resources\SuratMasuks;

use App\Enums\DerajatKecepatan;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\CreateSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\EditSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\ListSuratMasuks;
use App\Models\KlasifikasiArsip;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Rules\TautanBerkasValid;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SuratMasukResource extends Resource
{
    protected static ?string $model = SuratMasuk::class;

    protected static ?string $slug = 'surat-masuk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'surat masuk';

    protected static ?string $pluralModelLabel = 'surat masuk';

    protected static string|\UnitEnum|null $navigationGroup = 'Persuratan';

    protected static ?string $recordTitleAttribute = 'nomor_agenda';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Surat')->columns(2)->schema([
                TextInput::make('nomor_agenda')->label('Nomor agenda')->disabled()->dehydrated(false)->visibleOn('edit'),
                DateTimePicker::make('tanggal_terima')->label('Tanggal terima')->default(now())->required(),
                TextInput::make('nomor_surat')->label('Nomor surat pengirim')->required()->maxLength(150),
                DatePicker::make('tanggal_surat')->label('Tanggal surat')->required()->maxDate(now()->addDay()),
                TextInput::make('asal')->label('Asal / pengirim')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('perihal')->required()->maxLength(500)->columnSpanFull(),
                Textarea::make('ringkasan')->label('Isi ringkas')->rows(3)->maxLength(5000)->columnSpanFull(),
                TextInput::make('lampiran')->maxLength(100),
            ]),
            Section::make('Klasifikasi')->columns(2)->schema([
                Select::make('klasifikasi_keamanan')->label('Klasifikasi keamanan')->options(KlasifikasiKeamanan::pilihan())->default('biasa')->required(),
                Select::make('derajat_kecepatan')->label('Derajat kecepatan')->options(DerajatKecepatan::pilihan())->default('biasa')->required(),
                Select::make('klasifikasi_arsip_id')->label('Klasifikasi arsip')->options(fn () => KlasifikasiArsip::untukPilihan())->searchable(),
                Select::make('unit_pengolah_id')->label('Unit pengolah')->options(fn () => UnitKerja::where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))->searchable(),
            ]),
            Section::make('Pindaian')->visibleOn('create')->schema([
                TextInput::make('pindaian_url')->label('Tautan pindaian surat')->url()->required()->maxLength(2048)
                    ->rule(new TautanBerkasValid)
                    ->helperText('Tautan satu berkas (Google Drive/Docs atau unsil.ac.id). Surat rahasia: bagikan terbatas.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_terima', 'desc')
            ->columns([
                TextColumn::make('nomor_agenda')->label('No. agenda')->searchable()->sortable(),
                TextColumn::make('tanggal_terima')->label('Diterima')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('asal')->searchable()->wrap(),
                TextColumn::make('perihal')->wrap()
                    ->formatStateUsing(fn (SuratMasuk $record) => $record->perihalUntuk(auth()->user()))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('klasifikasi_keamanan', 'not like', '%rahasia%')->where('perihal', 'like', "%{$search}%"))),
                TextColumn::make('klasifikasi_keamanan')->label('Keamanan')->badge()->formatStateUsing(fn (KlasifikasiKeamanan $state) => $state->label()),
                TextColumn::make('derajat_kecepatan')->label('Kecepatan')->badge()->formatStateUsing(fn (DerajatKecepatan $state) => $state->label()),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusSuratMasuk $state) => $state->label()),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->schema([DatePicker::make('dari')->label('Dari tanggal'), DatePicker::make('sampai')->label('Sampai tanggal')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_terima', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_terima', '<=', $d))),
                Filter::make('asal')
                    ->schema([TextInput::make('asal')->label('Asal mengandung')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['asal'] ?? null, fn (Builder $q, string $a) => $q->where('asal', 'like', "%{$a}%"))),
                SelectFilter::make('klasifikasi_keamanan')->label('Keamanan')->options(KlasifikasiKeamanan::pilihan()),
                SelectFilter::make('status')->options(StatusSuratMasuk::pilihan()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [SuratMasukTautanRelationManager::class, DisposisiRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuratMasuks::route('/'),
            'create' => CreateSuratMasuk::route('/create'),
            'edit' => EditSuratMasuk::route('/{record}/edit'),
        ];
    }
}
