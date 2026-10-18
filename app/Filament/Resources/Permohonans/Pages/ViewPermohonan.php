<?php

namespace App\Filament\Resources\Permohonans\Pages;

use App\Actions\Permohonan\BuatPengantarRektorat;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\KirimHasilPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\SetujuiPembina;
use App\Actions\Permohonan\TerbitkanIzin;
use App\Actions\Permohonan\TolakPermohonan;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Filament\Resources\Naskahs\NaskahResource;
use App\Filament\Resources\Permohonans\PermohonanResource;
use App\Models\Jabatan;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use App\Models\UnitPembuat;
use App\Rules\TautanBerkasValid;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class ViewPermohonan extends ViewRecord
{
    protected static string $resource = PermohonanResource::class;

    private function permohonan(): Permohonan
    {
        /** @var Permohonan $permohonan */
        $permohonan = $this->record;

        return $permohonan;
    }

    /** Menjalankan aksi, menampilkan galat validasi sebagai notifikasi, lalu menyegarkan halaman. */
    private function jalankan(callable $aksi, string $judul): void
    {
        try {
            $aksi();
            Notification::make()->title($judul)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }

        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        $pengguna = fn () => auth()->user();

        return [
            Action::make('setujuiPembina')->label('Setujui (pembina)')->color('success')
                ->visible(fn () => $pengguna()->can('setujuiPembina', $this->permohonan()))->requiresConfirmation()
                ->action(fn () => $this->jalankan(fn () => app(SetujuiPembina::class)->jalankan($this->permohonan(), $pengguna()), 'Disetujui pembina')),
            Action::make('validasi')->label('Validasi')->color('success')
                ->visible(fn () => $pengguna()->can('validasi', $this->permohonan()))->requiresConfirmation()
                ->modalDescription('Permohonan akan diteruskan ke dekan untuk disposisi.')
                ->action(fn () => $this->jalankan(fn () => app(ValidasiPermohonan::class)->jalankan($this->permohonan(), $pengguna()), 'Permohonan divalidasi')),
            Action::make('kembalikan')->label('Kembalikan untuk revisi')->color('warning')
                ->visible(fn () => $pengguna()->can('putusTahapAwal', $this->permohonan()))
                ->schema([Textarea::make('catatan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(KembalikanPermohonan::class)->jalankan($this->permohonan(), $pengguna(), $data['catatan']), 'Permohonan dikembalikan')),
            Action::make('disposisiDekan')->label('Disposisikan ke Wakil Dekan')->color('success')
                ->visible(fn () => $pengguna()->can('disposisiDekan', $this->permohonan()))
                ->schema([
                    CheckboxList::make('jabatan')->label('Wakil Dekan tujuan')->required()->live()
                        ->options(fn () => Jabatan::where('kode', 'like', 'wd-%')->orderBy('urutan')->pluck('nama', 'id')),
                    // Mode hanya relevan bila lebih dari satu WD dipilih (BR-25).
                    Radio::make('mode_wd')->label('Aturan persetujuan')->options(Permohonan::MODE_WD)->default(Permohonan::MODE_WD_SEMUA)
                        ->visible(fn (Get $get) => count($get('jabatan') ?? []) > 1),
                    Textarea::make('catatan')->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(DisposisiPermohonan::class)->jalankan($this->permohonan(), $pengguna(), $data['jabatan'], $data['catatan'] ?? null, $data['mode_wd'] ?? Permohonan::MODE_WD_SEMUA), 'Permohonan didisposisikan')),
            Action::make('tolakDekan')->label('Tolak (dekan)')->color('danger')
                ->visible(fn () => $pengguna()->can('disposisiDekan', $this->permohonan()))
                ->schema([Textarea::make('alasan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(DisposisiPermohonan::class)->tolak($this->permohonan(), $pengguna(), $data['alasan']), 'Permohonan ditolak')),
            Action::make('setujuWd')->label('Setujui (WD)')->color('success')
                ->visible(fn () => $pengguna()->can('putusWd', $this->permohonan()))
                ->schema([Textarea::make('catatan')->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(PutusanWd::class)->jalankan($this->permohonan(), $pengguna(), PersetujuanWd::SETUJU, $data['catatan'] ?? null), 'Putusan setuju dicatat')),
            Action::make('tolakWd')->label('Tolak (WD)')->color('danger')
                ->visible(fn () => $pengguna()->can('putusWd', $this->permohonan()))
                ->schema([Textarea::make('catatan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(PutusanWd::class)->jalankan($this->permohonan(), $pengguna(), PersetujuanWd::TOLAK, $data['catatan']), 'Permohonan ditolak')),
            Action::make('rekomendasi')->label('Rekomendasikan & tugaskan pembuat surat')->color('success')
                ->visible(fn () => $pengguna()->can('rekomendasiKasubag', $this->permohonan()))
                ->fillForm(fn () => ['unit_pembuat_id' => $this->permohonan()->jenis->unit_pembuat_bawaan_id])
                ->schema([
                    Select::make('unit_pembuat_id')->label('Unit pembuat surat')->searchable()
                        ->options(fn () => UnitPembuat::where('aktif', true)->orderBy('nama')->pluck('nama', 'id'))
                        ->required(fn () => UnitPembuat::where('aktif', true)->exists())
                        ->helperText('Bawaan dari jenis layanan; ubah bila surat dibuat unit lain. Kosong hanya bila belum ada unit (jalur admin persuratan).'),
                    Textarea::make('catatan')->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(RekomendasiKasubag::class)->jalankan($this->permohonan(), $pengguna(), $data['catatan'] ?? null, $data['unit_pembuat_id'] ?: null), 'Direkomendasikan dan ditugaskan')),
            Action::make('tolakKasubag')->label('Tolak (kasubag)')->color('danger')
                ->visible(fn () => $pengguna()->can('rekomendasiKasubag', $this->permohonan()))
                ->schema([Textarea::make('alasan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(RekomendasiKasubag::class)->tolak($this->permohonan(), $pengguna(), $data['alasan']), 'Permohonan ditolak')),
            Action::make('terbitkanIzin')->label('Buat draf surat izin')->color('success')
                ->visible(fn () => $pengguna()->can('terbitkanIzin', $this->permohonan()))->requiresConfirmation()
                ->modalDescription('Draf naskah dibuat dari templat jenis permohonan; sunting lalu ajukan paraf/tanda tangan.')
                ->action(function () use ($pengguna) {
                    $this->jalankan(function () use ($pengguna) {
                        $naskah = app(TerbitkanIzin::class)->jalankan($this->permohonan(), $pengguna());
                        $this->redirect(NaskahResource::getUrl('edit', ['record' => $naskah]));
                    }, 'Draf surat izin dibuat');
                }),
            Action::make('kirimHasil')->label('Unggah hasil & kirim ke pengirim')->color('success')
                ->visible(fn () => $pengguna()->can('kirimHasil', $this->permohonan()))
                ->modalDescription('Permohonan langsung selesai dan pengirim diberi tahu. Hasil berupa tautan (Google Drive/Docs atau unsil.ac.id), bukan unggahan; bagikan sebagai "Siapa saja yang memiliki link" agar dapat dibuka pengirim.')
                ->schema([
                    TextInput::make('url')->label('Tautan hasil cetak')->required()->url()->maxLength(2048)->rule(new TautanBerkasValid),
                    Textarea::make('catatan')->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(KirimHasilPermohonan::class)->jalankan($this->permohonan(), $pengguna(), $data['url'], $data['catatan'] ?? null), 'Hasil dikirim; permohonan selesai')),
            Action::make('pengantarRektorat')->label('Buat surat pengantar ke rektorat')
                ->visible(fn () => $pengguna()->can('pengantarRektorat', $this->permohonan()) && $this->permohonan()->jenis->butuh_fasilitas_rektorat)
                ->action(function () use ($pengguna) {
                    $this->jalankan(function () use ($pengguna) {
                        $naskah = app(BuatPengantarRektorat::class)->jalankan($this->permohonan(), $pengguna());
                        $this->redirect(NaskahResource::getUrl('edit', ['record' => $naskah]));
                    }, 'Draf surat pengantar dibuat');
                }),
            Action::make('tolak')->label('Tolak')->color('danger')
                ->visible(fn () => $pengguna()->can('putusTahapAwal', $this->permohonan()))
                ->schema([Textarea::make('alasan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(TolakPermohonan::class)->jalankan($this->permohonan(), $pengguna(), $data['alasan']), 'Permohonan ditolak; ruangan dilepas')),
        ];
    }
}
