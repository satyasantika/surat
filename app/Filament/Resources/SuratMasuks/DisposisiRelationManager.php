<?php

namespace App\Filament\Resources\SuratMasuks;

use App\Enums\StatusDisposisiPenerima;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Status disposisi tiap penerima (hanya-baca). */
class DisposisiRelationManager extends RelationManager
{
    protected static string $relationship = 'penerimaDisposisi';

    protected static ?string $title = 'Disposisi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'jabatan', 'disposisi.dari']))
            ->defaultSort('disposisi_penerima.created_at')
            ->columns([
                TextColumn::make('user.name')->label('Penerima'),
                TextColumn::make('jabatan.nama')->label('Jabatan')->placeholder('—'),
                TextColumn::make('disposisi.dari.name')->label('Dari'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusDisposisiPenerima $state) => $state->label()),
                TextColumn::make('disposisi.batas_waktu')->label('Batas waktu')->dateTime('d M Y H:i'),
                IconColumn::make('terlambat')->boolean(),
                TextColumn::make('laporan_tindak_lanjut')->label('Laporan')->limit(60)->placeholder('—'),
            ]);
    }
}
