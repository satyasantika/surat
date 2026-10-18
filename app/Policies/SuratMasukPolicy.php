<?php

namespace App\Policies;

use App\Enums\StatusSuratMasuk;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\User;

/**
 * BR-04: surat rahasia/sangat rahasia hanya untuk dekan, penerima disposisi, dan super-admin
 * (Gate::before). Admin persuratan hanya melihat metadata registrasi (lihatMetadata), bukan isi/tautan.
 * Dekan hanya melihat surat yang sudah diteruskan staf khusus (`diteruskan_pada`).
 */
class SuratMasukPolicy
{
    /** Melihat daftar surat masuk di panel. */
    public function viewAny(User $pelaku): bool
    {
        return $this->melihatSemua($pelaku, null);
    }

    /** Membuka isi surat (ringkasan, pindaian). */
    public function view(User $pelaku, SuratMasuk $surat): bool
    {
        if ($surat->klasifikasi_keamanan->tertutup()) {
            return ($pelaku->hasRole('dekan') && $surat->sudahDiteruskan()) || $this->penerimaDisposisi($pelaku, $surat);
        }

        return $this->melihatSemua($pelaku, $surat) || $this->penerimaDisposisi($pelaku, $surat);
    }

    /** Melihat baris pada daftar (metadata registrasi). */
    public function lihatMetadata(User $pelaku, SuratMasuk $surat): bool
    {
        return $this->view($pelaku, $surat) || $this->melihatSemua($pelaku, $surat);
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

    /** Staf khusus meneruskan surat yang baru diregistrasi ke dekan (sekali). */
    public function teruskan(User $pelaku, SuratMasuk $surat): bool
    {
        return $pelaku->can('masuk.teruskan') && $this->lihatMetadata($pelaku, $surat)
            && ! $surat->sudahDiteruskan() && $surat->status === StatusSuratMasuk::Diterima;
    }

    /** Mengarsipkan surat selesai (RS-09): metadata saja, jadi admin boleh walau suratnya rahasia. */
    public function arsipkan(User $pelaku, SuratMasuk $surat): bool
    {
        return $pelaku->can('masuk.registrasi') && $this->lihatMetadata($pelaku, $surat);
    }

    /** Kasubag menugaskan unit pembuat atas surat yang sedang didisposisikan (BR-26); keterkaitan disposisi diperiksa Action. */
    public function tugaskanPembuat(User $pelaku, SuratMasuk $surat): bool
    {
        return $pelaku->hasRole('kasubag') && $pelaku->can('disposisi.teruskan') && $surat->status === StatusSuratMasuk::Didisposisikan;
    }

    /** Petugas unit pembuat yang ditugaskan mengirim hasil cetak kepada pengirim (BR-28). */
    public function kirimHasil(User $pelaku, SuratMasuk $surat): bool
    {
        return $surat->status === StatusSuratMasuk::Didisposisikan
            && $surat->unit_pembuat_id !== null
            && $pelaku->unitPembuat()->whereKey($surat->unit_pembuat_id)->exists();
    }

    public function delete(User $pelaku, SuratMasuk $surat): bool
    {
        return false;
    }

    /** $surat null = daftar (dekan tetap boleh membuka daftar; barisnya disaring scope `tampakBagi`). */
    private function melihatSemua(User $pelaku, ?SuratMasuk $surat): bool
    {
        return ($pelaku->hasRole('dekan') && ($surat === null || $surat->sudahDiteruskan()))
            || $pelaku->can('masuk.registrasi')
            || ($pelaku->hasRole('operator-layanan') && $pelaku->can('masuk.lihat'));
    }

    /** Penerima pada disposisi mana pun (awal maupun lanjutan) atas surat ini. */
    protected function penerimaDisposisi(User $pelaku, SuratMasuk $surat): bool
    {
        return DisposisiPenerima::query()
            ->where('user_id', $pelaku->getKey())
            ->whereHas('disposisi', fn ($q) => $q->where('surat_masuk_id', $surat->getKey()))
            ->exists();
    }
}
