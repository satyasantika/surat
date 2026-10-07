<?php

namespace App\Filament\Resources\RegisterSuratKeluars;

use App\Enums\StatusNaskah;
use App\Filament\Resources\RegisterSuratKeluars\Pages\ListRegisterSuratKeluars;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\RegisterNomor;
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

/** Register surat keluar (RS-08): otomatis dari naskah bernomor; hanya-baca. */
class RegisterSuratKeluarResource extends Resource
{
    protected static ?string $model = Naskah::class;

    protected static ?string $slug = 'register-surat-keluar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'register surat keluar';

    protected static ?string $pluralModelLabel = 'register surat keluar';

    protected static string|\UnitEnum|null $navigationGroup = 'Register & arsip';

    protected static ?int $navigationSort = 1;

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

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Naskah::query()
            ->whereNotNull('nomor')
            ->whereIn('status', [StatusNaskah::Ditandatangani->value, StatusNaskah::Terbit->value, StatusNaskah::Dibatalkan->value]);

        return $query;
    }

    /** Perihal naskah non-biasa hanya terlihat oleh yang berhak membuka naskahnya. */
    public static function perihalUntuk(Naskah $naskah): string
    {
        return $naskah->klasifikasi_keamanan->value === 'biasa' || auth()->user()?->can('view', $naskah)
            ? $naskah->perihal
            : '[DIBATASI]';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['jenis', 'penandaTanganUser', 'tujuan']))
            ->defaultSort('tanggal_naskah', 'desc')
            ->columns([
                TextColumn::make('nomor')->searchable()->sortable(),
                TextColumn::make('tanggal_naskah')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('jenis.nama')->label('Jenis'),
                TextColumn::make('perihal')->wrap()->formatStateUsing(fn (Naskah $record) => self::perihalUntuk($record))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('klasifikasi_keamanan', 'biasa')->where('perihal', 'like', "%{$search}%")),
                TextColumn::make('tujuan')->label('Tujuan')->formatStateUsing(fn (Naskah $record) => $record->tujuan->pluck('nama')->implode('; '))->wrap(),
                TextColumn::make('penandaTanganUser.name')->label('Penanda tangan')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusNaskah $state) => $state->label())
                    ->color(fn (StatusNaskah $state) => $state === StatusNaskah::Dibatalkan ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([DatePicker::make('dari')->label('Dari tanggal'), DatePicker::make('sampai')->label('Sampai tanggal')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_naskah', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_naskah', '<=', $d))),
                SelectFilter::make('register')->label('Register')
                    ->options(fn () => RegisterNomor::orderBy('nama')->pluck('nama', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('jenis', fn (Builder $q) => $q->where('register_nomor_id', $data['value']));
                        }

                        return $query;
                    }),
                SelectFilter::make('jenis_naskah_id')->label('Jenis')->options(fn () => JenisNaskah::orderBy('nama')->pluck('nama', 'id')),
                SelectFilter::make('status')->options([
                    StatusNaskah::Ditandatangani->value => 'Ditandatangani', StatusNaskah::Terbit->value => 'Terbit', StatusNaskah::Dibatalkan->value => 'Dibatalkan',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRegisterSuratKeluars::route('/')];
    }
}
