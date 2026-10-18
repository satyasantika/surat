<?php

namespace App\Models\Concerns;

use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Support\KodeLacak;

/**
 * Kode lacak acak dibuat saat baris dibuat (pengusulan/registrasi), unik di seluruh permohonan dan surat masuk.
 * Kode berfungsi sebagai kunci akses halaman publik, jadi disembunyikan dari serialisasi dan jejak audit (model
 * menambahkannya ke $hidden); baca langsung lewat atribut `kode_lacak`.
 */
trait MemilikiKodeLacak
{
    protected static function bootMemilikiKodeLacak(): void
    {
        static::creating(function (self $model) {
            if (blank($model->kode_lacak)) {
                $model->kode_lacak = KodeLacak::baru(fn (string $k) => Permohonan::withoutGlobalScopes()->where('kode_lacak', $k)->exists()
                    || SuratMasuk::withoutGlobalScopes()->withTrashed()->where('kode_lacak', $k)->exists());
            }
        });
    }

    /** Tautan lacak publik berkode. */
    public function tautanLacak(): string
    {
        return route('lacak.tampil', ['kode' => KodeLacak::tampil($this->kode_lacak)]);
    }
}
