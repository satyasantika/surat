<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Pengguna\MulaiImpersonasi;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable(),
                TextColumn::make('nip_nim')->label('NIP/NIM')->searchable(),
                TextColumn::make('roles.name')->label('Peran')->badge(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Aktif'),
                SelectFilter::make('peran')->label('Peran')
                    ->options(array_combine(array_keys(PeranDanIzinSeeder::PERAN), array_keys(PeranDanIzinSeeder::PERAN)))
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']));
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('masukSebagai')->label('Masuk sebagai')->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->visible(fn (User $record) => (bool) auth()->user()?->can('impersonasi') && ! $record->is(auth()->user()) && $record->aktif && ! $record->hasRole('super-admin'))
                    ->requiresConfirmation()
                    ->action(function (User $record, $livewire) {
                        app(MulaiImpersonasi::class)->jalankan(auth()->user(), $record);

                        return $livewire->redirect(url('admin'));
                    }),
            ]);
    }
}
