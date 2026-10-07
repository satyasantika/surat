<?php

namespace App\Policies;

use App\Models\Naskah;
use App\Models\Permohonan;
use App\Models\User;

class NaskahPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->canAny(['naskah.draf', 'naskah.paraf', 'naskah.tandatangan', 'nomor.terbitkan']);
    }

    public function view(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->can('nomor.terbitkan')
            || $naskah->penyusun_id === $pelaku->getKey()
            || $this->pemangkuPenandaTangan($pelaku, $naskah)
            || $naskah->paraf()->where('user_id', $pelaku->getKey())->exists()
            || $this->pengurusPemohon($pelaku, $naskah);
    }

    /** Pembatalan naskah yang sudah bernomor (BR-06); nomornya tidak dipakai ulang. */
    public function batalkan(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->can('naskah.batalkan')
            && in_array($naskah->status->value, ['ditandatangani', 'terbit'], true);
    }

    /** Mencatat pindaian naskah bertanda tangan basah (hanya admin penomoran). */
    public function catatPindaian(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->can('nomor.terbitkan')
            && $naskah->mode_tanda_tangan === 'basah'
            && $naskah->snapshot !== null
            && in_array($naskah->status->value, ['ditandatangani', 'terbit'], true);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('naskah.draf');
    }

    public function update(User $pelaku, Naskah $naskah): bool
    {
        return $naskah->penyusun_id === $pelaku->getKey() && $naskah->status->dapatDiubah();
    }

    public function delete(User $pelaku, Naskah $naskah): bool
    {
        return $naskah->penyusun_id === $pelaku->getKey() && $naskah->status->value === 'draf';
    }

    /** Pengurus aktif ormawa pemohon boleh mengunduh surat izin yang sudah terbit. */
    protected function pengurusPemohon(User $pelaku, Naskah $naskah): bool
    {
        if ($naskah->permohonan_id === null || $naskah->status->value !== 'terbit') {
            return false;
        }

        $ormawaId = Permohonan::whereKey($naskah->permohonan_id)->value('ormawa_id');

        return $ormawaId !== null && $pelaku->ormawaAktif()->contains('id', $ormawaId);
    }

    protected function pemangkuPenandaTangan(User $pelaku, Naskah $naskah): bool
    {
        return $pelaku->jabatanAktif()->contains('id', $naskah->penanda_tangan_jabatan_id);
    }
}
