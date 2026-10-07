<?php

namespace App\Filament\Resources\Kabars;

use App\Actions\Kabar\SimpanKabar;
use App\Actions\Kabar\TerbitkanKabar;
use App\Actions\Kabar\TolakKabar;
use App\Filament\Resources\Kabars\Pages\ManageKabars;
use App\Models\Kabar;
use App\Models\Ormawa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KabarResource extends Resource
{
    protected static ?string $model = Kabar::class;

    protected static ?string $slug = 'kabar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $modelLabel = 'kabar';

    protected static ?string $pluralModelLabel = 'kabar';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ormawa_id')->label('Ormawa (kosong = kabar fakultas)')
                ->options(fn () => Ormawa::orderBy('nama')->pluck('nama', 'id'))->searchable()->nullable(),
            TextInput::make('judul')->required()->maxLength(200),
            TextInput::make('subjudul')->maxLength(250),
            RichEditor::make('isi')->required()->toolbarButtons(['bold', 'italic', 'underline', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link', 'undo', 'redo']),
            TagsInput::make('tag'),
            TextInput::make('sampul')->label('Tautan foto sampul')->url()->maxLength(2048)
                ->afterStateHydrated(fn (TextInput $component, ?Kabar $record) => $record?->loadMissing('tautan') && $component->state($record->tautan->firstWhere('jenis', 'foto')?->url)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('ormawa'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('judul')->searchable()->wrap(),
                TextColumn::make('ormawa.nama')->label('Ormawa')->placeholder('Fakultas'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Kabar::STATUS[$state] ?? $state),
                TextColumn::make('terbit_pada')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(Kabar::STATUS)])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Kabar $record, array $data) => app(SimpanKabar::class)->jalankan(auth()->user(), $data, $record)),
                Action::make('terbitkan')->label('Terbitkan')->color('success')->requiresConfirmation()
                    ->visible(fn (Kabar $record) => auth()->user()?->can('terbitkan', $record))
                    ->action(function (Kabar $record) {
                        app(TerbitkanKabar::class)->jalankan($record, auth()->user());
                        Notification::make()->title('Kabar diterbitkan')->success()->send();
                    }),
                Action::make('tolak')->label('Tolak')->color('danger')
                    ->visible(fn (Kabar $record) => auth()->user()?->can('tolak', $record))
                    ->schema([Textarea::make('catatan')->label('Catatan untuk penulis')->required()->maxLength(2000)])
                    ->action(function (Kabar $record, array $data) {
                        app(TolakKabar::class)->jalankan($record, auth()->user(), $data['catatan']);
                        Notification::make()->title('Kabar ditolak')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageKabars::route('/')];
    }
}
