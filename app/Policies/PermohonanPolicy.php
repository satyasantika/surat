<?php

namespace App\Policies;

use App\Enums\StatusPermohonan;
use App\Models\Naskah;
use App\Models\Permohonan;
use App\Models\User;

/**
 * Pengurus aktif melihat permohonan ormawanya; pembina atas binaannya; admin, dekan, WD, kasubag melihat
 * semua untuk memutus. Data pribadi penanggung jawab (BR-18) hanya untuk admin validasi, ketua/sekretaris,
 * dan pengaju.
 */
class PermohonanPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->canAny(['permohonan.lihat', 'permohonan.validasi', 'permohonan.putuskan', 'permohonan.setujui-pembina'])
            || $pelaku->unitPembuat()->exists();
    }

    public function view(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->canAny(['permohonan.lihat', 'permohonan.validasi', 'permohonan.putuskan'])
            || $this->petugasUnit($pelaku, $permohonan)
            || $permohonan->ormawa->pembina_user_id === $pelaku->getKey()
            || $pelaku->ormawaAktif()->contains('id', $permohonan->ormawa_id);
    }

    public function lihatDataPribadi(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->can('permohonan.validasi')
            || $permohonan->diajukan_oleh === $pelaku->getKey()
            || $pelaku->dapatMengelolaOrmawa($permohonan->ormawa);
    }

    public function setujuiPembina(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->can('permohonan.setujui-pembina')
            && $permohonan->ormawa->pembina_user_id === $pelaku->getKey()
            && $permohonan->status === StatusPermohonan::PersetujuanPembina;
    }

    public function validasi(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->can('permohonan.validasi') && $permohonan->status === StatusPermohonan::ValidasiAdmin;
    }

    /** Mengembalikan/menolak pada tahap pembina (oleh pembina binaan) atau tahap validasi (oleh admin). */
    public function putusTahapAwal(User $pelaku, Permohonan $permohonan): bool
    {
        return $this->setujuiPembina($pelaku, $permohonan) || $this->validasi($pelaku, $permohonan);
    }

    /** Pengurus aktif ormawa merevisi lalu mengajukan ulang permohonan yang dikembalikan. */
    public function revisi(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->status === StatusPermohonan::Dikembalikan
            && $pelaku->can('permohonan.ajukan')
            && $pelaku->ormawaAktif()->contains('id', $permohonan->ormawa_id);
    }

    /** Pembatalan oleh pengurus aktif (ketua/sekretaris atau pengaju) sebelum penerbitan. */
    public function batalkan(User $pelaku, Permohonan $permohonan): bool
    {
        return ! $permohonan->status->akhir()
            && ! in_array($permohonan->status, [StatusPermohonan::Pembuatan, StatusPermohonan::Penerbitan], true)
            && $pelaku->ormawaAktif()->contains('id', $permohonan->ormawa_id)
            && ($permohonan->diajukan_oleh === $pelaku->getKey() || $pelaku->dapatMengelolaOrmawa($permohonan->ormawa));
    }

    /** Dekan mendisposisikan (atau menolak) permohonan yang sudah divalidasi. */
    public function disposisiDekan(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->status === StatusPermohonan::DisposisiDekan
            && $pelaku->can('disposisi.buat') && $pelaku->hasAnyRole(['dekan', 'super-admin']);
    }

    /** WD tujuan disposisi yang masih menunggu putusan (pemangku jabatan, termasuk Plt). */
    public function putusWd(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->status === StatusPermohonan::PersetujuanWd
            && $pelaku->can('permohonan.putuskan')
            && $permohonan->persetujuanWd()->where('putusan', 'menunggu')->whereIn('jabatan_id', $pelaku->jabatanAktif()->pluck('id'))->exists();
    }

    public function rekomendasiKasubag(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->status === StatusPermohonan::RekomendasiKasubag
            && $pelaku->can('permohonan.putuskan') && $pelaku->hasRole('kasubag')
            && $pelaku->jabatanAktif()->contains('kode', 'kasubag-umum');
    }

    /**
     * Pembuat draf surat izin (BR-26). Bila kasubag menugaskan unit (status pembuatan, atau penerbitan dengan unit
     * tertaut), hanya petugas unit itu (dan super-admin) yang boleh; admin persuratan tidak otomatis. Tanpa unit
     * (jalur lama), admin dengan izin permohonan.validasi tetap membuat draf pada status penerbitan.
     */
    public function terbitkanIzin(User $pelaku, Permohonan $permohonan): bool
    {
        if (! in_array($permohonan->status, [StatusPermohonan::Pembuatan, StatusPermohonan::Penerbitan], true) || ! $pelaku->can('naskah.draf')) {
            return false;
        }

        if ($permohonan->naskah_izin_id !== null && ! Naskah::whereKey($permohonan->naskah_izin_id)->where('status', 'dibatalkan')->exists()) {
            return false;
        }

        return $permohonan->unit_pembuat_id !== null
            ? $this->petugasUnit($pelaku, $permohonan)
            : $permohonan->status === StatusPermohonan::Penerbitan && $pelaku->can('permohonan.validasi');
    }

    /** Pengguna adalah petugas aktif unit pembuat yang ditugaskan pada permohonan. */
    public function petugasUnit(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->unit_pembuat_id !== null
            && $pelaku->unitPembuat()->whereKey($permohonan->unit_pembuat_id)->exists();
    }

    /** Petugas unit yang ditugaskan mengirim hasil cetak kepada pengirim (BR-28); super-admin lewat Gate::before. */
    public function kirimHasil(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->status === StatusPermohonan::Pembuatan && $this->petugasUnit($pelaku, $permohonan);
    }

    public function pengantarRektorat(User $pelaku, Permohonan $permohonan): bool
    {
        return in_array($permohonan->status, [StatusPermohonan::Pembuatan, StatusPermohonan::Penerbitan, StatusPermohonan::Selesai], true)
            && ($pelaku->can('permohonan.validasi') || $this->petugasUnit($pelaku, $permohonan)) && $pelaku->can('naskah.draf');
    }
}
