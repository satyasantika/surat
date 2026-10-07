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
        return $pelaku->canAny(['permohonan.validasi', 'permohonan.putuskan', 'permohonan.setujui-pembina']);
    }

    public function view(User $pelaku, Permohonan $permohonan): bool
    {
        return $pelaku->canAny(['permohonan.validasi', 'permohonan.putuskan'])
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
            && $permohonan->status !== StatusPermohonan::Penerbitan
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

    /** Admin membuat draf surat izin setelah rekomendasi kasubag. */
    public function terbitkanIzin(User $pelaku, Permohonan $permohonan): bool
    {
        return $permohonan->status === StatusPermohonan::Penerbitan && $pelaku->can('permohonan.validasi') && $pelaku->can('naskah.draf')
            && ($permohonan->naskah_izin_id === null || Naskah::whereKey($permohonan->naskah_izin_id)->where('status', 'dibatalkan')->exists());
    }

    public function pengantarRektorat(User $pelaku, Permohonan $permohonan): bool
    {
        return in_array($permohonan->status, [StatusPermohonan::Penerbitan, StatusPermohonan::Selesai], true)
            && $pelaku->can('permohonan.validasi') && $pelaku->can('naskah.draf');
    }
}
