<?php

namespace App\Filament\Resources\Jabatans\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PemangkuRelationManager extends RelationManager
{
    protected static string $relationship = 'pemangku';

    protected static ?string $title = 'Pemangku jabatan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Pengguna')->relationship('user', 'name')->required()->searchable()->preload(),
            DatePicker::make('mulai')->required(),
            DatePicker::make('selesai')->afterOrEqual('mulai')->helperText('Kosongkan bila masih menjabat.'),
            Toggle::make('plt')->label('Pelaksana tugas (Plt)'),
            TextInput::make('nomor_sk')->label('Nomor SK')->maxLength(100),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->defaultSort('mulai', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Pemangku'),
                TextColumn::make('mulai')->date('d M Y'),
                TextColumn::make('selesai')->date('d M Y')->placeholder('masih menjabat'),
                IconColumn::make('plt')->label('Plt')->boolean(),
                TextColumn::make('nomor_sk')->label('Nomor SK')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
