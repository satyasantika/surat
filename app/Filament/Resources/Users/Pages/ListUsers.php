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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /** Batas baris akun baru yang ditampilkan pada tabel pratinjau (impor tetap memproses semuanya). */
    public const int MAKS_PRATINJAU = 100;

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
            ->modalWidth('5xl')
            ->schema([
                Textarea::make('teks')
                    ->label('Data pengguna')
                    ->rows(7)
                    ->required()
                    ->live(debounce: 600)
                    ->extraInputAttributes(['style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem;', 'spellcheck' => 'false'])
                    ->placeholder("Nama\tSurel\tPeran\tNIP/NIM\tTelepon\nBudi Santoso\tbudi.santoso@unsil.ac.id\tpegawai\t198001012010011001\t081234567890\nSiti Aminah\tsiti@student.unsil.ac.id\tpengurus-ormawa|mahasiswa\t221010001"),
                View::make('filament.pengguna.pratinjau-impor-tempel')
                    ->viewData(fn (Get $get): array => ['pratinjau' => self::pratinjau((string) $get('teks'))]),
            ])
            ->action(function (array $data): void {
                /** @var User $pelaku */
                $pelaku = auth()->user();
                // Surel atur kata sandi sementara tidak dikirim (sakelarnya disembunyikan); akun bersurel unsil masuk dengan Google.
                $hasil = app(ImporPenggunaTempel::class)->handle((string) $data['teks'], $pelaku, kirimTautan: false);

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

    /**
     * Data pratinjau untuk view: keadaan, akun baru (dibatasi MAKS_PRATINJAU), surel dilewati, dan galat per baris.
     *
     * @return array{keadaan: 'kosong'|'galat'|'siap', baru: list<array{baris: int, name: string, email: string, peran: list<string>, nip_nim: ?string, telepon: ?string}>, jumlah_baru: int, dilewati: list<string>, galat: list<array{baris: ?int, pesan: string}>}
     */
    public static function pratinjau(string $teks): array
    {
        $kosong = ['keadaan' => 'kosong', 'baru' => [], 'jumlah_baru' => 0, 'dilewati' => [], 'galat' => []];

        if (trim($teks) === '') {
            return $kosong;
        }

        /** @var User $pelaku */
        $pelaku = auth()->user();
        $hasil = app(ImporPenggunaTempel::class)->periksa($teks, $pelaku);

        $galat = array_map(function (string $pesan): array {
            // Pesan berbentuk "Baris n: …" dipecah agar nomor baris tampil di kolom sendiri.
            return preg_match('/^Baris (\d+): (.*)$/su', $pesan, $m)
                ? ['baris' => (int) $m[1], 'pesan' => Str::ucfirst($m[2])]
                : ['baris' => null, 'pesan' => $pesan];
        }, $hasil['galat']);

        return [
            'keadaan' => $galat !== [] ? 'galat' : 'siap',
            'baru' => array_slice($hasil['baru'], 0, self::MAKS_PRATINJAU),
            'jumlah_baru' => count($hasil['baru']),
            'dilewati' => $hasil['dilewati'],
            'galat' => $galat,
        ];
    }
}
