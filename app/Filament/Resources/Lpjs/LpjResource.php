<?php

namespace App\Filament\Resources\Lpjs;

use App\Filament\Resources\Lpjs\Pages\ListLpjs;
use App\Filament\Resources\Lpjs\Pages\ViewLpj;
use App\Models\Lpj;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LpjResource extends Resource
{
    protected static ?string $model = Lpj::class;

    protected static ?string $slug = 'lpj';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $modelLabel = 'LPJ';

    protected static ?string $pluralModelLabel = 'LPJ';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Admin dan penilai melihat semua; pembina hanya binaannya.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Lpj::query();
        $pengguna = auth()->user();

        if ($pengguna && ! $pengguna->canAny(['permohonan.validasi', 'lpj.nilai'])) {
            $query->whereHas('permohonan.ormawa', fn (Builder $q) => $q->where('pembina_user_id', $pengguna->getKey()));
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('LPJ')->columns(2)->schema([
                TextEntry::make('permohonan.nomor')->label('Permohonan'),
                TextEntry::make('permohonan.ormawa.nama')->label('Ormawa'),
                TextEntry::make('permohonan.nama_kegiatan')->label('Kegiatan')->columnSpanFull(),
                TextEntry::make('status')->badge(),
                TextEntry::make('batas_waktu')->date('d M Y'),
                TextEntry::make('tanggal_pelaksanaan')->date('d M Y')->placeholder('—'),
                TextEntry::make('jumlah_peserta')->placeholder('—'),
                TextEntry::make('ringkasan')->placeholder('—')->columnSpanFull(),
                TextEntry::make('kendala')->placeholder('—'),
                TextEntry::make('solusi')->placeholder('—'),
                TextEntry::make('rekomendasi')->placeholder('—')->columnSpanFull(),
                TextEntry::make('nilai_akhir')->placeholder('Belum final'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('permohonan.ormawa'))
            ->defaultSort('batas_waktu')
            ->columns([
                TextColumn::make('permohonan.ormawa.nama')->label('Ormawa')->searchable(),
                TextColumn::make('permohonan.nama_kegiatan')->label('Kegiatan')->wrap(),
                TextColumn::make('status')->badge(),
                TextColumn::make('batas_waktu')->date('d M Y')->sortable(),
                TextColumn::make('nilai_akhir')->placeholder('—')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(['draf' => 'Draf', 'diajukan' => 'Diajukan', 'dinilai' => 'Dinilai'])])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [NilaiRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListLpjs::route('/'), 'view' => ViewLpj::route('/{record}')];
    }
}
