<?php

namespace App\Filament\Resources\Permohonans;

use App\Enums\StatusPermohonan;
use App\Filament\Resources\Permohonans\Pages\ListPermohonans;
use App\Filament\Resources\Permohonans\Pages\ViewPermohonan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\Permohonan;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PermohonanResource extends Resource
{
    protected static ?string $model = Permohonan::class;

    protected static ?string $slug = 'permohonan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'permohonan';

    protected static ?string $pluralModelLabel = 'permohonan ormawa';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Admin, pimpinan, dan pemegang izin baca (permohonan.lihat, mis. staf khusus) melihat semua; pembina hanya permohonan ormawa binaannya; petugas unit pembuat hanya yang ditugaskan ke unitnya.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Permohonan::query();
        $pengguna = auth()->user();

        if ($pengguna && ! $pengguna->canAny(['permohonan.lihat', 'permohonan.validasi', 'permohonan.putuskan'])) {
            $unitIds = $pengguna->unitPembuat()->pluck('unit_pembuat.id');

            $query->where(fn (Builder $w) => $w
                ->whereHas('ormawa', fn (Builder $q) => $q->where('pembina_user_id', $pengguna->getKey()))
                ->orWhereIn('unit_pembuat_id', $unitIds));
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Permohonan')->columns(2)->schema([
                TextEntry::make('nomor'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (StatusPermohonan $state) => $state->label()),
                TextEntry::make('ormawa.nama')->label('Ormawa'),
                TextEntry::make('jenis.nama')->label('Jenis'),
                TextEntry::make('nama_kegiatan')->columnSpanFull(),
                TextEntry::make('perihal')->columnSpanFull(),
                TextEntry::make('tanggal_mulai')->date('d M Y'),
                TextEntry::make('tanggal_selesai')->date('d M Y'),
                TextEntry::make('deskripsi')->columnSpanFull(),
                TextEntry::make('alasan_mendesak')->placeholder('—')->columnSpanFull(),
                TextEntry::make('mode_wd')->label('Aturan persetujuan WD')->formatStateUsing(fn (?string $state) => Permohonan::MODE_WD[$state ?? 'semua'] ?? $state),
                TextEntry::make('unitPembuat.nama')->label('Unit pembuat surat')->placeholder('—'),
                TextEntry::make('pengaju.name')->label('Diajukan oleh'),
                TextEntry::make('diajukan_pada')->dateTime('d M Y H:i'),
                // Untuk diberikan kepada pengirim yang tidak punya akun (BR-27); kode ini kunci akses, jadi hanya staf berwenang.
                TextEntry::make('tautan_lacak')->label('Tautan lacak publik')->columnSpanFull()->copyable()
                    ->state(fn (Permohonan $record) => $record->tautanLacak())
                    ->visible(fn () => auth()->user()?->canAny(['permohonan.lihat', 'permohonan.validasi', 'masuk.teruskan']) ?? false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['ormawa', 'jenis']))
            ->defaultSort('diajukan_pada', 'desc')
            ->columns([
                TextColumn::make('nomor')->searchable()->sortable(),
                TextColumn::make('ormawa.nama')->label('Ormawa')->searchable(),
                TextColumn::make('nama_kegiatan')->searchable()->wrap(),
                TextColumn::make('jenis.nama')->label('Jenis')->toggleable(),
                TextColumn::make('tanggal_mulai')->label('Kegiatan')->date('d M Y')->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusPermohonan $state) => $state->label()),
                TextColumn::make('diajukan_pada')->label('Diajukan')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('ormawa_id')->label('Ormawa')->options(fn () => Ormawa::orderBy('nama')->pluck('nama', 'id'))->searchable(),
                SelectFilter::make('jenis_permohonan_id')->label('Jenis')->options(fn () => JenisPermohonan::orderBy('nama')->pluck('nama', 'id')),
                Filter::make('tanggal')
                    ->schema([DatePicker::make('dari')->label('Kegiatan dari'), DatePicker::make('sampai')->label('Kegiatan sampai')])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_mulai', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('tanggal_mulai', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PermohonanRiwayatRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermohonans::route('/'),
            'view' => ViewPermohonan::route('/{record}'),
        ];
    }
}
