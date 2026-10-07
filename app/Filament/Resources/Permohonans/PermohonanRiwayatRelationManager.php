<?php

namespace App\Filament\Resources\Permohonans;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Riwayat transisi permohonan (hanya-baca). */
class PermohonanRiwayatRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayat';

    protected static ?string $title = 'Riwayat';

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
            ->modifyQueryUsing(fn ($query) => $query->with('pelaku'))
            ->defaultSort('riwayat_permohonan.created_at')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                TextColumn::make('dari_status')->label('Dari')->badge()->placeholder('—'),
                TextColumn::make('ke_status')->label('Ke')->badge(),
                TextColumn::make('pelaku.name')->label('Oleh')->placeholder('—'),
                TextColumn::make('catatan')->wrap()->placeholder('—'),
            ]);
    }
}
