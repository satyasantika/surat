<?php

namespace App\Filament\Resources\Ormawas;

use App\Actions\Ormawa\TautkanAkunPengurus;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PengurusRelationManager extends RelationManager
{
    protected static string $relationship = 'pengurus';

    protected static ?string $title = 'Pengurus';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nama')->required()->maxLength(150),
            Select::make('jabatan')->options(PengurusOrmawa::JABATAN)->required(),
            TextInput::make('jabatan_teks')->label('Nama jabatan (bebas)')->maxLength(100),
            TextInput::make('nim')->label('NIM')->maxLength(20)->helperText('Data pribadi; dipakai untuk menautkan akun.'),
            TextInput::make('prodi')->maxLength(100),
            TextInput::make('telepon')->tel()->maxLength(20)->helperText('Data pribadi; disimpan terenkripsi.'),
            Select::make('sk_kepengurusan_id')->label('SK kepengurusan')
                ->options(fn () => $this->ormawa()->sk()->orderByDesc('periode_mulai')->pluck('nomor_sk', 'id')),
            DatePicker::make('mulai'),
            DatePicker::make('selesai')->afterOrEqual('mulai'),
            Toggle::make('tampil_publik')->label('Tampil di halaman publik')->default(true),
            Toggle::make('narahubung')->label('Narahubung'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['ormawa', 'user']))
            ->defaultSort('jabatan')
            ->columns([
                TextColumn::make('nama')->searchable(),
                TextColumn::make('jabatan')->badge()->formatStateUsing(fn (PengurusOrmawa $record) => $record->jabatan_teks ?: (PengurusOrmawa::JABATAN[$record->jabatan] ?? $record->jabatan)),
                TextColumn::make('nim')->label('NIM')->state(fn (PengurusOrmawa $record) => $this->bolehLihatPribadi($record) ? ($record->nim ?: '—') : 'disembunyikan'),
                TextColumn::make('telepon')->state(fn (PengurusOrmawa $record) => $this->bolehLihatPribadi($record) ? ($record->telepon ?: '—') : 'disembunyikan')->toggleable(),
                IconColumn::make('tertaut')->label('Akun')->boolean()->state(fn (PengurusOrmawa $record) => $record->user_id !== null),
                IconColumn::make('tampil_publik')->label('Publik')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('tautkanSemua')->label('Tautkan semua akun yang cocok')->icon('heroicon-o-link')
                    ->visible(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->requiresConfirmation()
                    ->action(function () {
                        $jumlah = app(TautkanAkunPengurus::class)->semuaDiOrmawa($this->ormawa(), auth()->user());
                        Notification::make()->title("{$jumlah} akun ditautkan")->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('tautkan')->label('Tautkan akun')->icon('heroicon-o-link')
                    ->visible(fn (PengurusOrmawa $record) => $record->user_id === null && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (PengurusOrmawa $record) {
                        try {
                            app(TautkanAkunPengurus::class)->jalankan($record, auth()->user());
                            Notification::make()->title('Akun ditautkan')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private function ormawa(): Ormawa
    {
        /** @var Ormawa $ormawa */
        $ormawa = $this->getOwnerRecord();

        return $ormawa;
    }

    private function bolehLihatPribadi(PengurusOrmawa $pengurus): bool
    {
        return auth()->user()?->can('lihatDataPribadi', $pengurus) ?? false;
    }
}
