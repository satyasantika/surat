<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

/**
 * @property array<int, array{kunci: string, label: string, tipe: string, wajib?: bool, opsi?: array<string, string>}> $variabel
 * @property array<int, string> $mode_tanda_tangan_diizinkan
 */
#[Fillable(['kode', 'nama', 'kelompok', 'register_nomor_id', 'templat_blade', 'versi_templat', 'variabel', 'mode_tanda_tangan_diizinkan', 'jabatan_penanda_tangan_bawaan_id', 'aktif'])]
class JenisNaskah extends Model
{
    use HasUuids, TercatatAktivitas;

    public const KELOMPOK = ['arahan' => 'Arahan', 'korespondensi' => 'Korespondensi', 'khusus' => 'Khusus', 'lainnya' => 'Lainnya'];

    public const TIPE_VARIABEL = ['text' => 'Teks', 'textarea' => 'Teks panjang', 'date' => 'Tanggal', 'number' => 'Angka', 'select' => 'Pilihan'];

    public const MODE_TANDA_TANGAN = ['basah' => 'Basah', 'visual' => 'Visual + QR', 'tte' => 'TTE tersertifikasi'];

    protected $table = 'jenis_naskah';

    protected $attributes = ['aktif' => true, 'versi_templat' => 1];

    protected function casts(): array
    {
        return [
            'variabel' => 'array',
            'mode_tanda_tangan_diizinkan' => 'array',
            'versi_templat' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $jenis) {
            if (! self::templatAda($jenis->templat_blade)) {
                throw ValidationException::withMessages([
                    'templat_blade' => "Templat {$jenis->templat_blade} tidak ditemukan di resources/views/naskah/.",
                ]);
            }
        });
    }

    /** Templat harus bernama naskah.{slug} dan berkasnya ada di resources/views/naskah/. */
    public static function templatAda(?string $nama): bool
    {
        return is_string($nama)
            && preg_match('/^naskah\.[a-z0-9][a-z0-9-]*$/', $nama) === 1
            && View::exists($nama);
    }

    /** @return BelongsTo<RegisterNomor, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(RegisterNomor::class, 'register_nomor_id');
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatanPenandaTanganBawaan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_penanda_tangan_bawaan_id');
    }
}
