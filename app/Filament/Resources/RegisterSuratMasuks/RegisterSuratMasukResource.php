<?php

namespace App\Filament\Resources\RegisterSuratMasuks;

use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusSuratMasuk;
use App\Filament\Resources\RegisterSuratMasuks\Pages\ListRegisterSuratMasuks;
use App\Models\SuratMasuk;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Register surat masuk (agenda): hanya-baca; perihal rahasia disamarkan bagi yang tidak berhak. */
class RegisterSuratMasukResource extends Resource
{
    protected static ?string $model = SuratMasuk::class;

    protected static ?string $slug = 'register-surat-masuk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'register surat masuk';

    protected static ?string $pluralModelLabel = 'register surat masuk';

    protected static string|\UnitEnum|null $navigationGroup = 'Register & arsip';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('arsip.lihat') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_terima', 'desc')
            ->columns([
                TextColumn::make('nomor_agenda')->label('No. agenda')->searchable()->sortable(),
                TextColumn::make('tanggal_terima')->label('Diterima')->dateTime('d M Y')->sortable(),
                TextColumn::make('nomor_surat')->label('No. surat')->searchable(),
                TextColumn::make('tanggal_surat')->label('Tgl. surat')->date('d M Y'),
                TextColumn::make('asal')->searchable()->wrap(),
                TextColumn::make('perihal')->wrap()->formatStateUsing(fn (SuratMasuk $record) => $record->perihalUntuk(auth()->user()))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('klasifikasi_keamanan', 'not like', '%rahasia%')->where('perihal', 'like', "%{$search}%")),
                TextColumn::make('klasifikasi_keamanan')->label('Keamanan')->badge()->formatStateUsing(fn (KlasifikasiKeamanan $state) => $state->label()),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusSuratMasuk $state) => $state->label()),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([DatePicker::make('dari')->label('Dari tanggal'), DatePicker::make('sampai')->label('Sampai tanggal')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_terima', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_terima', '<=', $d))),
                SelectFilter::make('klasifikasi_keamanan')->label('Keamanan')->options(KlasifikasiKeamanan::pilihan()),
                SelectFilter::make('status')->options(StatusSuratMasuk::pilihan()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRegisterSuratMasuks::route('/')];
    }
}
