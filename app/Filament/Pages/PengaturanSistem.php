<?php

namespace App\Filament\Pages;

use App\Support\Pengaturan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property Schema $form
 */
class PengaturanSistem extends Page
{
    protected string $view = 'filament.pages.pengaturan-sistem';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan sistem';

    protected static ?string $title = 'Pengaturan sistem';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $slug = 'pengaturan';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('pengaturan.kelola');
    }

    public function mount(): void
    {
        $nilai = [];
        foreach (Pengaturan::BAWAAN as $kunci => $isi) {
            $nilai[$kunci] = Pengaturan::get($kunci);
        }

        $this->form->fill($nilai);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Permohonan ormawa')->columns(2)->schema([
                TextInput::make('min_hari_sebelum_kegiatan')->label('Minimal hari pengajuan sebelum kegiatan')->numeric()->integer()->minValue(0)->maxValue(365)->required(),
                Toggle::make('persetujuan_pembina_aktif')->label('Wajib persetujuan pembina'),
            ]),
            Section::make('LPJ')->columns(3)->schema([
                TextInput::make('batas_hari_lpj')->label('Batas hari LPJ setelah kegiatan')->numeric()->integer()->minValue(1)->maxValue(365)->required(),
                TextInput::make('toleransi_lpj_hari')->label('Toleransi (hari)')->numeric()->integer()->minValue(0)->maxValue(90)->required(),
                Toggle::make('blokir_lpj_terlambat')->label('Blokir pengajuan bila LPJ terlambat'),
            ]),
            Section::make('Ruangan')->schema([
                Select::make('layanan_ruangan')->options(['lokal' => 'Lokal', 'aset_api' => 'API Aset'])->required(),
                Repeater::make('sesi')->label('Sesi pemakaian ruangan')->minItems(1)->schema([
                    TextInput::make('kode')->required()->alphaDash()->maxLength(20)->distinct(),
                    TextInput::make('mulai')->required()->rule('date_format:H:i')->placeholder('06:00'),
                    TextInput::make('selesai')->required()->rule('date_format:H:i')->after('mulai')->placeholder('12:00'),
                ])->columns(3),
            ]),
            Section::make('Kop surat')->schema([
                Repeater::make('kop_surat')->label('Baris kop')->minItems(1)->simple(
                    TextInput::make('baris')->required()->maxLength(150),
                ),
            ]),
            Section::make('Notifikasi')->schema([
                Toggle::make('wa_aktif')->label('Kirim notifikasi WhatsApp'),
                TextInput::make('jam_pengingat_disposisi')->label('Pengingat disposisi mendekati batas (jam)')->numeric()->integer()->minValue(1)->maxValue(720)->required(),
                TextInput::make('hari_tertahan_permohonan')->label('Pengingat permohonan tertahan (hari)')->numeric()->integer()->minValue(1)->maxValue(90)->required(),
            ]),
            Section::make('Keamanan')->schema([
                Toggle::make('mfa_wajib')->label('Wajibkan MFA untuk pejabat dan admin')
                    ->helperText('Bila menyala, peran pejabat dan admin harus memasang aplikasi autentikator sebelum memakai panel.')
                    ->visible(fn (): bool => static::superAdmin()),
            ]),
            Section::make('Kiriman eksternal')->columns(2)->schema([
                TextInput::make('batas_kiriman_harian')->label('Batas pengiriman per hari per akun')->numeric()->integer()->minValue(1)->maxValue(50)->required(),
                TextInput::make('batas_tujuan_kiriman')->label('Batas tujuan perantara per pengiriman')->numeric()->integer()->minValue(1)->maxValue(50)->required(),
            ]),
        ]);
    }

    protected static function superAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    public function simpan(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $data['kop_surat'] = array_values(array_filter((array) $data['kop_surat'], 'is_string'));

        // saklar MFA hanya boleh diubah super-admin, apa pun yang dikirim klien
        if (! static::superAdmin()) {
            unset($data['mfa_wajib']);
        }

        foreach (array_keys(Pengaturan::BAWAAN) as $kunci) {
            if (array_key_exists($kunci, $data)) {
                Pengaturan::set($kunci, $data[$kunci]);
            }
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [Action::make('simpan')->label('Simpan')->submit('simpan')];
    }
}
