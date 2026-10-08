<?php

namespace App\Filament\Imports;

use App\Actions\Pengguna\SimpanPengguna;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * Impor massal pengguna lewat CSV. Kolom: name, email, nip_nim, telepon, peran, aktif.
 * Peran dipisah titik-koma (mis. "pegawai;operator-layanan"). Seluruh aturan otorisasi dan validasi
 * SimpanPengguna tetap ditegakkan di sini (bukan diduplikasi): peran terbatas (super-admin,
 * admin-persuratan) hanya boleh diberikan oleh pelaku ber-peran super-admin; baris yang ditolak
 * dicatat sebagai gagal, baris lain tetap diproses. Akun baru mendapat kata sandi acak dan surel
 * pengaturan kata sandi, sama seperti pembuatan satuan lewat formulir.
 */
class PenggunaImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')->label('Nama')->requiredMapping()->rules(['required', 'string', 'max:150'])->example('Budi Santoso'),
            ImportColumn::make('email')->label('Surel')->requiredMapping()
                ->rules(['required', 'email', 'max:255', new SurelDomainUnsil])->example('budi.santoso@unsil.ac.id'),
            ImportColumn::make('nip_nim')->label('NIP/NIM')->rules(['nullable', 'string', 'max:30'])->example('198001012010011001'),
            ImportColumn::make('telepon')->label('Telepon')->rules(['nullable', 'string', 'max:20'])->example('081234567890'),
            ImportColumn::make('peran')->label('Peran')->rules(['nullable', 'string'])
                ->example('pegawai')->exampleHeader('peran (pisahkan dengan ;)'),
            ImportColumn::make('aktif')->label('Aktif')->boolean()->rules(['nullable', 'boolean'])->example('1'),
        ];
    }

    public function resolveRecord(): User
    {
        return User::firstOrNew(['email' => $this->data['email']]);
    }

    /** Pengisian atribut ditangani SimpanPengguna, bukan pengisian langsung kolom per kolom. */
    public function fillRecord(): void {}

    public function saveRecord(): void
    {
        /** @var User|null $pelaku */
        $pelaku = Auth::user();

        if (! $pelaku) {
            throw new RowImportFailedException('Sesi tidak valid; ulangi proses impor.');
        }

        $peran = array_values(array_filter(array_map('trim', explode(';', (string) ($this->data['peran'] ?? '')))));

        foreach ($peran as $nama) {
            if (! array_key_exists($nama, PeranDanIzinSeeder::PERAN)) {
                throw new RowImportFailedException("Peran '{$nama}' tidak dikenal.");
            }
        }

        $data = [
            'name' => $this->data['name'],
            'email' => $this->data['email'],
            'nip_nim' => $this->data['nip_nim'] ?? null,
            'telepon' => $this->data['telepon'] ?? null,
            'aktif' => $this->data['aktif'] ?? true,
            'peran' => $peran,
            'izin_langsung' => [],
        ];

        try {
            $this->record = app(SimpanPengguna::class)->jalankan($this->record->exists ? $this->record : null, $data, $pelaku);
        } catch (ValidationException $e) {
            throw new RowImportFailedException(collect($e->errors())->flatten()->implode(' '));
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = Number::format($import->successful_rows).' baris pengguna berhasil diimpor.';

        if ($gagal = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($gagal).' baris gagal; unduh berkas baris gagal untuk rinciannya.';
        }

        return $body;
    }
}
