<?php

namespace App\Filament\Resources\Lpjs;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Matriks nilai per rubrik dan penilai (hanya-baca). */
class NilaiRelationManager extends RelationManager
{
    protected static string $relationship = 'nilai';

    protected static ?string $title = 'Nilai';

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
            ->modifyQueryUsing(fn ($query) => $query->with(['rubrik', 'jabatan', 'penilai']))
            ->columns([
                TextColumn::make('rubrik.nama')->label('Rubrik'),
                TextColumn::make('jabatan.nama')->label('Jabatan penilai'),
                TextColumn::make('penilai.name')->label('Penilai'),
                TextColumn::make('nilai')->label('Nilai'),
                TextColumn::make('catatan')->wrap()->placeholder('—'),
            ]);
    }
}
