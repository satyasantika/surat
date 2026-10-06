<?php

namespace App\Filament\Resources\Aktivitas\Tables;

use App\Models\Aktivitas;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AktivitasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('log_name')->label('Modul')->badge()->searchable(),
                TextColumn::make('event')->label('Peristiwa')->badge(),
                TextColumn::make('description')->label('Keterangan')->searchable()->wrap(),
                TextColumn::make('causer.name')->label('Pelaku')->placeholder('sistem'),
                TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')->toggleable(),
                TextColumn::make('properties')->label('Perubahan')->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE))->limit(80)->tooltip(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Modul')
                    ->options(fn () => Aktivitas::query()->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->all()),
                SelectFilter::make('causer_id')->label('Pelaku')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))),
            ]);
    }
}
