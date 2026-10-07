<?php

namespace App\Filament\Resources\Naskahs;

use App\Enums\DerajatKecepatan;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusNaskah;
use App\Filament\Resources\Naskahs\Pages\CreateNaskah;
use App\Filament\Resources\Naskahs\Pages\EditNaskah;
use App\Filament\Resources\Naskahs\Pages\ListNaskahs;
use App\Filament\Resources\Naskahs\Pages\ViewNaskah;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NaskahResource extends Resource
{
    protected static ?string $model = Naskah::class;

    protected static ?string $slug = 'naskah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'naskah keluar';

    protected static ?string $pluralModelLabel = 'naskah keluar';

    protected static string|\UnitEnum|null $navigationGroup = 'Persuratan';

    protected static ?string $recordTitleAttribute = 'perihal';

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Naskah::query()->terlihatOleh(auth()->user());

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Naskah')->columns(2)->schema([
                Select::make('jenis_naskah_id')->label('Jenis naskah')
                    ->options(fn () => JenisNaskah::where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                    ->required()->live()->disabledOn('edit')->dehydrated()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $jenis = $state ? JenisNaskah::find($state) : null;
                        $set('penanda_tangan_jabatan_id', $jenis?->jabatan_penanda_tangan_bawaan_id);
                        $set('mode_tanda_tangan', $jenis ? ($jenis->mode_tanda_tangan_diizinkan[0] ?? null) : null);
                        $set('data', []);
                    }),
                TextInput::make('perihal')->required()->maxLength(500),
                Select::make('klasifikasi_keamanan')->label('Klasifikasi keamanan')->options(KlasifikasiKeamanan::pilihan())->default('biasa')->required(),
                Select::make('derajat_kecepatan')->label('Sifat/derajat kecepatan')->options(DerajatKecepatan::pilihan())->default('biasa')->required(),
                Select::make('klasifikasi_arsip_id')->label('Klasifikasi arsip')->options(fn () => KlasifikasiArsip::untukPilihan())->searchable(),
            ]),
            Section::make('Isian templat')->columns(2)->schema(fn (Get $get): array => self::fieldVariabel($get('jenis_naskah_id')))
                ->visible(fn (Get $get) => filled($get('jenis_naskah_id'))),
            Section::make('Isi')->schema([
                RichEditor::make('isi')->label('Isi naskah')
                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'h2', 'h3', 'blockquote', 'table', 'undo', 'redo'])
                    ->helperText('Konten disanitasi saat disimpan; tag selain daftar putih dibuang.'),
            ]),
            Section::make('Tujuan dan tembusan')->schema([
                self::repeaterTujuan('tujuan', 'Tujuan (Yth.)'),
                self::repeaterTujuan('tembusan', 'Tembusan'),
            ]),
            Section::make('Penanda tangan')->columns(3)->schema([
                Select::make('penanda_tangan_jabatan_id')->label('Jabatan penanda tangan')
                    ->options(fn () => Jabatan::where('dapat_menandatangani', true)->orderBy('urutan')->pluck('nama', 'id'))->required(),
                Select::make('atas_nama')->label('Atas nama')->options(['an' => 'a.n.', 'ub' => 'u.b.', 'plt' => 'Plt.', 'plh' => 'Plh.'])->placeholder('Langsung'),
                Select::make('mode_tanda_tangan')->label('Mode tanda tangan')
                    ->options(fn (Get $get) => collect(($j = JenisNaskah::find($get('jenis_naskah_id'))) ? $j->mode_tanda_tangan_diizinkan : [])
                        ->mapWithKeys(fn (string $m) => [$m => JenisNaskah::MODE_TANDA_TANGAN[$m] ?? $m])->all())
                    ->required(),
            ]),
        ]);
    }

    /** @return array<int, Component> */
    private static function fieldVariabel(?string $jenisId): array
    {
        $jenis = $jenisId ? JenisNaskah::find($jenisId) : null;

        return collect($jenis ? $jenis->variabel : [])->map(function (array $v) {
            $nama = "data.{$v['kunci']}";
            $field = match ($v['tipe']) {
                'textarea' => Textarea::make($nama)->rows(3)->maxLength(5000)->columnSpanFull(),
                'date' => DatePicker::make($nama),
                'number' => TextInput::make($nama)->numeric(),
                'select' => Select::make($nama)->options($v['opsi'] ?? []),
                default => TextInput::make($nama)->maxLength(255),
            };

            return $field->label($v['label'])->required((bool) ($v['wajib'] ?? false));
        })->all();
    }

    private static function repeaterTujuan(string $nama, string $label): Repeater
    {
        return Repeater::make($nama)->label($label)->default([])->addActionLabel('Tambah')
            ->schema([
                TextInput::make('nama')->label('Nama/jabatan')->required()->maxLength(255),
                Select::make('user_id')->label('Penerima internal (opsional)')
                    ->options(fn () => User::where('aktif', true)->orderBy('name')->pluck('name', 'id'))->searchable(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['jenis', 'penyusun']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->placeholder('—')->searchable(),
                TextColumn::make('jenis.nama')->label('Jenis'),
                TextColumn::make('perihal')->searchable()->wrap(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (StatusNaskah $state) => $state->label()),
                TextColumn::make('penyusun.name')->label('Penyusun'),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ])
            ->filters([SelectFilter::make('status')->options(StatusNaskah::pilihan())])
            ->recordActions([EditAction::make(), ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Naskah')->columns(2)->schema([
                TextEntry::make('nomor')->placeholder('Belum bernomor'),
                TextEntry::make('tanggal_naskah')->date('d F Y')->placeholder('—'),
                TextEntry::make('jenis.nama')->label('Jenis'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (StatusNaskah $state) => $state->label()),
                TextEntry::make('perihal')->columnSpanFull(),
                TextEntry::make('penandaTanganJabatan.nama')->label('Jabatan penanda tangan'),
                TextEntry::make('penandaTanganUser.name')->label('Ditandatangani oleh')->placeholder('—'),
                TextEntry::make('mode_tanda_tangan')->label('Mode')->formatStateUsing(fn (string $state) => JenisNaskah::MODE_TANDA_TANGAN[$state] ?? $state),
                TextEntry::make('hash_pdf')->label('SHA-256 PDF')->placeholder('—')->copyable()->fontFamily('mono')->columnSpanFull(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [NaskahTautanRelationManager::class, RiwayatRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewNaskah::route('/{record}'),
            'index' => ListNaskahs::route('/'),
            'create' => CreateNaskah::route('/create'),
            'edit' => EditNaskah::route('/{record}/edit'),
        ];
    }
}
