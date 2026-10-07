<?php

namespace App\Filament\Resources\ImporLogs;

use App\Filament\Resources\ImporLogs\Pages\ListImporLogs;
use App\Models\ImporLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Log migrasi OrmawaHub (hanya baca, super-admin). */
class ImporLogResource extends Resource
{
    protected static ?string $model = ImporLog::class;

    protected static ?string $slug = 'log-migrasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'log migrasi';

    protected static ?string $pluralModelLabel = 'Log migrasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('batch')->label('Batch')->limit(8)->tooltip(fn (ImporLog $r) => $r->batch)->searchable(),
                TextColumn::make('sheet')->searchable(),
                TextColumn::make('id_lama')->label('Id lama')->searchable(),
                TextColumn::make('tabel_baru')->label('Tabel baru')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    ImporLog::OK => 'success', ImporLog::PERINGATAN => 'warning', ImporLog::GALAT => 'danger', default => 'gray',
                }),
                TextColumn::make('pesan')->wrap()->limit(160)->placeholder('—')->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['ok' => 'ok', 'peringatan' => 'peringatan', 'lewati' => 'lewati', 'galat' => 'galat']),
                SelectFilter::make('sheet')->options(fn () => ImporLog::distinct()->orderBy('sheet')->pluck('sheet', 'sheet')->all()),
                SelectFilter::make('batch')->options(fn () => ImporLog::selectRaw('batch, min(created_at) as t')->groupBy('batch')->orderByDesc('t')->limit(20)->pluck('batch', 'batch')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListImporLogs::route('/')];
    }
}
