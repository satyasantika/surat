<?php

namespace App\Filament\Resources\Ormawas;

use App\Contracts\PenyimpananBerkas;
use App\Models\Ormawa;
use App\Models\SkKepengurusan;
use App\Rules\TautanBerkasValid;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SkRelationManager extends RelationManager
{
    protected static string $relationship = 'sk';

    protected static ?string $title = 'SK kepengurusan';

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
        return $schema->components([
            TextInput::make('nomor_sk')->label('Nomor SK')->required()->maxLength(150),
            DatePicker::make('tanggal_sk')->label('Tanggal SK')->required(),
            DatePicker::make('periode_mulai')->label('Periode mulai')->required(),
            DatePicker::make('periode_selesai')->label('Periode selesai')->required()->afterOrEqual('periode_mulai'),
            TextInput::make('disahkan_oleh')->label('Disahkan oleh')->maxLength(150),
            TextInput::make('url_sk')->label('Tautan dokumen SK')->url()->maxLength(2048)->rule(new TautanBerkasValid)->visibleOn('create')
                ->helperText('Disarankan berkas PDF final yang tidak diedit lagi.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tautan'))
            ->defaultSort('periode_mulai', 'desc')
            ->columns([
                TextColumn::make('nomor_sk')->label('Nomor SK'),
                TextColumn::make('tanggal_sk')->label('Tanggal')->date('d M Y'),
                TextColumn::make('periode_mulai')->label('Mulai')->date('d M Y'),
                TextColumn::make('periode_selesai')->label('Selesai')->date('d M Y'),
                IconColumn::make('berlaku')->label('Berlaku')->boolean()->state(fn (SkKepengurusan $record) => $record->berlaku()),
            ])
            ->headerActions([
                CreateAction::make()->using(function (array $data, RelationManager $livewire): Model {
                    $url = $data['url_sk'] ?? null;
                    unset($data['url_sk']);

                    /** @var Ormawa $ormawa */
                    $ormawa = $livewire->getOwnerRecord();
                    $sk = $ormawa->sk()->create($data);

                    if (filled($url)) {
                        app(PenyimpananBerkas::class)->simpan($sk, 'sk', $url, 'SK kepengurusan', auth()->user());
                    }

                    return $sk;
                }),
            ])
            ->recordActions([
                Action::make('bukaSk')->label('Buka SK')->icon('heroicon-o-document')
                    ->visible(fn (SkKepengurusan $record) => $record->tautan->isNotEmpty() && (auth()->user()?->can('view', $record) ?? false))
                    ->url(fn (SkKepengurusan $record) => route('berkas.buka', $record->tautan->first()), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
