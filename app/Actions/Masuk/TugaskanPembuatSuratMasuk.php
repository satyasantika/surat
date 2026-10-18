<?php

namespace App\Actions\Masuk;

use App\Actions\Disposisi\BuatDisposisi;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\UnitPembuat;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Kasubag menugaskan unit pembuat atas surat masuk (prodi/eksternal) lewat rantai disposisi yang ada (BR-26):
 * disposisi lanjutan dari disposisi yang diterima kasubag, ditujukan kepada setiap petugas aktif unit. Unit dicatat
 * pada surat agar hanya petugas unit itu yang dapat mengirim hasil (BR-28). Penugasan ulang ke unit lain diizinkan
 * selama surat belum selesai.
 */
class TugaskanPembuatSuratMasuk
{
    public function __construct(private readonly BuatDisposisi $disposisi) {}

    public function jalankan(SuratMasuk $surat, User $kasubag, DisposisiPenerima $induk, string $unitPembuatId, ?string $catatan = null): Disposisi
    {
        if ($catatan !== null && mb_strlen($catatan) > 2000) {
            throw ValidationException::withMessages(['catatan' => 'Catatan paling banyak 2000 karakter.']);
        }

        return DB::transaction(function () use ($surat, $kasubag, $induk, $unitPembuatId, $catatan) {
            $surat = SuratMasuk::lockForUpdate()->findOrFail($surat->getKey());
            Gate::forUser($kasubag)->authorize('tugaskanPembuat', $surat);

            $induk->loadMissing('disposisi');

            if ($induk->user_id !== $kasubag->getKey() || $induk->disposisi->surat_masuk_id !== $surat->getKey()) {
                throw new AuthorizationException('Disposisi ini bukan milik Anda atau bukan atas surat ini.');
            }

            $unit = UnitPembuat::whereKey($unitPembuatId)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['unit_pembuat_id' => 'Unit pembuat tidak ditemukan.']);

            if (! $unit->aktif) {
                throw ValidationException::withMessages(['unit_pembuat_id' => "Unit pembuat {$unit->nama} tidak aktif."]);
            }

            $petugas = $unit->petugasAktif()->whereKeyNot($kasubag->getKey())->pluck('users.id')->all();

            if ($petugas === []) {
                throw ValidationException::withMessages(['unit_pembuat_id' => "Unit pembuat {$unit->nama} belum memiliki petugas aktif."]);
            }

            $disposisi = $this->disposisi->jalankan($surat, $kasubag, $petugas, ['tindak_lanjuti'], $catatan ?: 'Buat surat jawaban/hasil, unggah tautan hasil, lalu kirim kepada pengirim.', null, $induk);

            $surat->forceFill(['unit_pembuat_id' => $unit->getKey()])->save();

            activity('surat-masuk')->event('tugaskan-pembuat')->performedOn($surat)->causedBy($kasubag)
                ->withProperties(['disposisi_id' => $disposisi->getKey(), 'unit' => $unit->nama, 'unit_id' => $unit->getKey()])
                ->log("Surat ditugaskan kepada unit pembuat {$unit->nama}");

            return $disposisi;
        });
    }
}
