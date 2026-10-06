<?php

namespace App\Filament\Resources\Aktivitas;

use App\Filament\Resources\Aktivitas\Pages\ListAktivitas;
use App\Filament\Resources\Aktivitas\Tables\AktivitasTable;
use App\Models\Aktivitas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Jejak audit, hanya-baca, hanya untuk super-admin (BR-22). */
class AktivitasResource extends Resource
{
    protected static ?string $model = Aktivitas::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'log aktivitas';

    protected static ?string $navigationLabel = 'Log aktivitas';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AktivitasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAktivitas::route('/'),
        ];
    }
}
