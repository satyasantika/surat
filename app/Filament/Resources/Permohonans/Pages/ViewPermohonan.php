<?php

namespace App\Filament\Resources\Permohonans\Pages;

use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\SetujuiPembina;
use App\Actions\Permohonan\TolakPermohonan;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Filament\Resources\Permohonans\PermohonanResource;
use App\Models\Jabatan;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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
                    CheckboxList::make('jabatan')->label('Wakil Dekan tujuan')->required()
                        ->options(fn () => Jabatan::where('kode', 'like', 'wd-%')->orderBy('urutan')->pluck('nama', 'id')),
                    Textarea::make('catatan')->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(DisposisiPermohonan::class)->jalankan($this->permohonan(), $pengguna(), $data['jabatan'], $data['catatan'] ?? null), 'Permohonan didisposisikan')),
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
            Action::make('rekomendasi')->label('Rekomendasikan terbit')->color('success')
                ->visible(fn () => $pengguna()->can('rekomendasiKasubag', $this->permohonan()))
                ->schema([Textarea::make('catatan')->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(RekomendasiKasubag::class)->jalankan($this->permohonan(), $pengguna(), $data['catatan'] ?? null), 'Direkomendasikan')),
            Action::make('tolakKasubag')->label('Tolak (kasubag)')->color('danger')
                ->visible(fn () => $pengguna()->can('rekomendasiKasubag', $this->permohonan()))
                ->schema([Textarea::make('alasan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(RekomendasiKasubag::class)->tolak($this->permohonan(), $pengguna(), $data['alasan']), 'Permohonan ditolak')),
            Action::make('tolak')->label('Tolak')->color('danger')
                ->visible(fn () => $pengguna()->can('putusTahapAwal', $this->permohonan()))
                ->schema([Textarea::make('alasan')->required()->maxLength(2000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(TolakPermohonan::class)->jalankan($this->permohonan(), $pengguna(), $data['alasan']), 'Permohonan ditolak; ruangan dilepas')),
        ];
    }
}
