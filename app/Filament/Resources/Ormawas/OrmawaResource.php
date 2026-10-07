<?php

namespace App\Filament\Resources\Ormawas;

use App\Filament\Resources\Ormawas\Pages\CreateOrmawa;
use App\Filament\Resources\Ormawas\Pages\EditOrmawa;
use App\Filament\Resources\Ormawas\Pages\ListOrmawas;
use App\Models\Ormawa;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrmawaResource extends Resource
{
    protected static ?string $model = Ormawa::class;

    protected static ?string $slug = 'ormawa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'ormawa';

    protected static ?string $pluralModelLabel = 'ormawa';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    protected static ?string $recordTitleAttribute = 'nama';

    /**
     * Pembina yang hanya berhak atas binaannya hanya melihat binaannya.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Ormawa::query();
        $pengguna = auth()->user();

        if ($pengguna && ! $pengguna->canAny(['ormawa.kelola', 'ormawa.lihat'])) {
            $query->where('pembina_user_id', $pengguna->getKey());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')->columns(2)->schema([
                TextInput::make('nama')->required()->maxLength(150)->unique(ignoreRecord: true),
                TextInput::make('slug')->maxLength(100)->unique(ignoreRecord: true)->alphaDash()->helperText('Kosongkan untuk dibuat otomatis dari nama.'),
                TextInput::make('singkatan')->maxLength(30),
                Select::make('tingkat')->options(Ormawa::TINGKAT)->required(),
                TextInput::make('prodi')->maxLength(100),
                TextInput::make('akun_media')->label('Akun media')->maxLength(100)->regex('/^@?[A-Za-z0-9._]+$/'),
                TextInput::make('surel_organisasi')->label('Surel organisasi')->email()->maxLength(150),
                Select::make('pembina_user_id')->label('Pembina')
                    ->options(fn () => User::role('pembina-ormawa')->where('aktif', true)->orderBy('name')->pluck('name', 'id'))->searchable(),
                Toggle::make('aktif')->default(true),
            ]),
            Section::make('Visi dan misi')->schema([
                Textarea::make('visi')->rows(3)->maxLength(5000),
                Textarea::make('misi')->rows(4)->maxLength(5000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pembina'))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')->searchable()->sortable(),
                TextColumn::make('singkatan')->placeholder('—'),
                TextColumn::make('tingkat')->badge()->formatStateUsing(fn (string $state) => Ormawa::TINGKAT[$state] ?? $state),
                TextColumn::make('pembina.name')->label('Pembina')->placeholder('—'),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('tingkat')->options(Ormawa::TINGKAT),
                TernaryFilter::make('aktif'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [SkRelationManager::class, OrmawaTautanRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrmawas::route('/'),
            'create' => CreateOrmawa::route('/create'),
            'edit' => EditOrmawa::route('/{record}/edit'),
        ];
    }
}
