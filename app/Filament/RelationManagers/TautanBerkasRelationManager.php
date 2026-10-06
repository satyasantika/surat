<?php

namespace App\Filament\RelationManagers;

use App\Rules\TautanBerkasValid;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengelola tautan berkas untuk model apa pun yang punya relasi `tautan` (morphMany). Hanya pemegang izin
 * `update` pada pemilik yang boleh menambah/mengubah; jenis tertutup (ttd_visual) tidak ditawarkan.
 */
class TautanBerkasRelationManager extends RelationManager
{
    protected static string $relationship = 'tautan';

    protected static ?string $title = 'Tautan berkas';

    /** @var list<string> */
    protected static array $jenis = ['pindaian', 'lampiran'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenis')->options(array_combine(static::$jenis, static::$jenis))->required(),
            TextInput::make('label')->maxLength(255),
            TextInput::make('url')->label('Tautan')->url()->required()->maxLength(2048)
                ->rule(new TautanBerkasValid)
                ->helperText('Google Drive/Docs, OneDrive, atau domain unsil.ac.id. Dokumen berisi data pribadi: bagikan terbatas ke domain unsil.ac.id.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenis')->badge(),
                TextColumn::make('label')->placeholder('—'),
                TextColumn::make('status_cek')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => ['belum' => 'Belum dicek', 'dapat_diakses' => 'Dapat diakses', 'tidak_dapat_diakses' => 'Tidak dapat diakses'][$state] ?? $state),
                TextColumn::make('created_at')->label('Ditambahkan')->since(),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['ditambahkan_oleh' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('update', $this->getOwnerRecord());
    }

    protected function canCreate(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }
}
