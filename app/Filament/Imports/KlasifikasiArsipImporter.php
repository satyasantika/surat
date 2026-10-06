<?php

namespace App\Filament\Imports;

use App\Models\KlasifikasiArsip;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Impor CSV klasifikasi arsip. Kolom: kode, nama, induk_kode, retensi_aktif, retensi_inaktif, keterangan_akhir.
 * Cantumkan baris induk sebelum anaknya (induk harus sudah ada saat baris anak diproses).
 */
class KlasifikasiArsipImporter extends Importer
{
    protected static ?string $model = KlasifikasiArsip::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('kode')->requiredMapping()->rules(['required', 'max:20'])->example('KM.03.02'),
            ImportColumn::make('nama')->requiredMapping()->rules(['required', 'max:255'])->example('Pembinaan kemahasiswaan'),
            ImportColumn::make('induk_kode')->fillRecordUsing(fn () => null)->rules(['nullable', 'max:20'])->example('KM.03'),
            ImportColumn::make('retensi_aktif')->fillRecordUsing(fn (KlasifikasiArsip $r, ?string $state) => $r->retensi_aktif_tahun = filled($state) ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:0', 'max:100'])->example('2'),
            ImportColumn::make('retensi_inaktif')->fillRecordUsing(fn (KlasifikasiArsip $r, ?string $state) => $r->retensi_inaktif_tahun = filled($state) ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:0', 'max:100'])->example('3'),
            ImportColumn::make('keterangan_akhir')->rules(['nullable', Rule::in(array_keys(KlasifikasiArsip::KETERANGAN_AKHIR))])->example('musnah'),
        ];
    }

    public function resolveRecord(): KlasifikasiArsip
    {
        return KlasifikasiArsip::firstOrNew(['kode' => $this->data['kode']]);
    }

    protected function afterSave(): void
    {
        $kodeInduk = trim((string) ($this->data['induk_kode'] ?? ''));

        if ($kodeInduk === '') {
            $this->record->forceFill(['induk_id' => null])->save();

            return;
        }

        $induk = KlasifikasiArsip::firstWhere('kode', $kodeInduk);

        if (! $induk) {
            throw new RowImportFailedException("Induk {$kodeInduk} belum ada; cantumkan induk sebelum anaknya.");
        }

        try {
            $this->record->forceFill(['induk_id' => $induk->getKey()])->save();
        } catch (ValidationException $e) {
            throw new RowImportFailedException($e->getMessage());
        }
    }

    public function getJobQueue(): ?string
    {
        return 'impor';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = Number::format($import->successful_rows).' baris klasifikasi arsip berhasil diimpor.';

        if ($gagal = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($gagal).' baris gagal; unduh berkas baris gagal untuk rinciannya.';
        }

        return $body;
    }
}
