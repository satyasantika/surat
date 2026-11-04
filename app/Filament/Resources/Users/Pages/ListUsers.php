<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Pengguna\ImporPenggunaTempel;
use App\Filament\Imports\PenggunaImporter;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiImporTempel(),
            ImportAction::make()->importer(PenggunaImporter::class)->label('Impor massal (CSV)'),
            CreateAction::make(),
        ];
    }

    /** Impor massal dari salin-tempel Excel/Google Sheets dengan pratinjau langsung; aturan ada di ImporPenggunaTempel. */
    protected function aksiImporTempel(): Action
    {
        return Action::make('imporTempel')
            ->label('Tempel dari Excel')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->visible(fn (): bool => UserResource::canCreate())
            ->modalHeading('Impor pengguna dari salin-tempel Excel')
            ->modalDescription('Salin baris dari Excel/Google Sheets lalu tempel. Kolom berurutan: nama, surel (@unsil.ac.id, @student.unsil.ac.id, atau @staff.unsil.ac.id), peran (beberapa dipisah "|"), NIP/NIM, telepon; hanya nama dan surel yang wajib. Surel yang sudah terdaftar dilewati, tidak diubah. Satu baris salah membatalkan seluruh impor.')
            ->modalSubmitActionLabel('Impor')
            ->modalWidth('3xl')
            ->schema([
                Textarea::make('teks')
                    ->label('Data pengguna')
                    ->rows(8)
                    ->required()
                    ->live(debounce: 600)
                    ->placeholder("Nama\tSurel\tPeran\tNIP/NIM\tTelepon\nBudi Santoso\tbudi.santoso@unsil.ac.id\tpegawai\t198001012010011001\t081234567890\nSiti Aminah\tsiti@student.unsil.ac.id\tpengurus-ormawa|mahasiswa\t221010001"),
                TextEntry::make('pratinjau')
                    ->label('Pratinjau')
                    ->state(fn (Get $get): array => self::ringkasPratinjau((string) $get('teks')))
                    ->listWithLineBreaks()
                    ->bulleted(),
                Toggle::make('kirim_tautan')
                    ->label('Kirim surel atur kata sandi ke akun baru')
                    ->helperText('Akun bersurel unsil tetap dapat langsung masuk dengan Google tanpa surel ini.')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                /** @var User $pelaku */
                $pelaku = auth()->user();
                $hasil = app(ImporPenggunaTempel::class)->handle((string) $data['teks'], $pelaku, (bool) ($data['kirim_tautan'] ?? true));

                if ($hasil['galat'] !== []) {
                    $sisa = count($hasil['galat']) - 15;

                    Notification::make()
                        ->title('Impor dibatalkan: tidak ada pengguna yang dibuat')
                        ->body(implode("\n", array_slice($hasil['galat'], 0, 15)).($sisa > 0 ? "\n…dan {$sisa} galat lain." : ''))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$hasil['dibuat']} pengguna dibuat, ".count($hasil['dilewati']).' dilewati (sudah terdaftar)')
                    ->success()
                    ->send();
            });
    }

    /** @return list<string> */
    public static function ringkasPratinjau(string $teks): array
    {
        if (trim($teks) === '') {
            return ['Tempel data untuk melihat pratinjau.'];
        }

        /** @var User $pelaku */
        $pelaku = auth()->user();
        $hasil = app(ImporPenggunaTempel::class)->periksa($teks, $pelaku);

        if ($hasil['galat'] !== []) {
            return ['Ada '.count($hasil['galat']).' galat; perbaiki sebelum impor:', ...array_slice($hasil['galat'], 0, 10)];
        }

        $baris = array_map(
            fn (array $akun): string => "{$akun['name']} <{$akun['email']}>".($akun['peran'] !== [] ? ' — '.implode(', ', $akun['peran']) : ' — tanpa peran'),
            array_slice($hasil['baru'], 0, 20),
        );

        return [
            count($hasil['baru']).' akun baru akan dibuat, '.count($hasil['dilewati']).' dilewati karena sudah terdaftar.',
            ...$baris,
            ...(count($hasil['baru']) > 20 ? ['…dan '.(count($hasil['baru']) - 20).' akun lain.'] : []),
        ];
    }
}
