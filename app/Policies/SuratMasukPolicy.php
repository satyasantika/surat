<?php

namespace App\Policies;

use App\Models\SuratMasuk;
use App\Models\User;

/**
 * BR-04: surat rahasia/sangat rahasia hanya untuk dekan, penerima disposisi, dan super-admin
 * (Gate::before). Admin persuratan hanya melihat metadata registrasi (lihatMetadata), bukan isi/tautan.
 */
class SuratMasukPolicy
{
    /** Melihat daftar surat masuk di panel. */
    public function viewAny(User $pelaku): bool
    {
        return $this->melihatSemua($pelaku);
    }

    /** Membuka isi surat (ringkasan, pindaian). */
    public function view(User $pelaku, SuratMasuk $surat): bool
    {
        if ($surat->klasifikasi_keamanan->tertutup()) {
            return $pelaku->hasRole('dekan') || $this->penerimaDisposisi($pelaku, $surat);
        }

        return $this->melihatSemua($pelaku) || $this->penerimaDisposisi($pelaku, $surat);
    }

    /** Melihat baris pada daftar (metadata registrasi). */
    public function lihatMetadata(User $pelaku, SuratMasuk $surat): bool
    {
        return $this->view($pelaku, $surat) || $this->melihatSemua($pelaku);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('masuk.registrasi');
    }

    public function update(User $pelaku, SuratMasuk $surat): bool
    {
        // Mengubah berarti membuka isi: admin persuratan tidak boleh mengubah surat rahasia.
        return $pelaku->can('masuk.registrasi') && $this->view($pelaku, $surat);
    }

    public function delete(User $pelaku, SuratMasuk $surat): bool
    {
        return false;
    }

    private function melihatSemua(User $pelaku): bool
    {
        return $pelaku->hasRole('dekan')
            || $pelaku->can('masuk.registrasi')
            || ($pelaku->hasRole('operator-layanan') && $pelaku->can('masuk.lihat'));
    }

    /** Diperluas pada F4.2 setelah tabel disposisi ada. */
    protected function penerimaDisposisi(User $pelaku, SuratMasuk $surat): bool
    {
        return false;
    }
}
