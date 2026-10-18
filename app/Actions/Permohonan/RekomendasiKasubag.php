<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\Permohonan;
use App\Models\UnitPembuat;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Kasubag merekomendasikan penerbitan surat izin dan menugaskan unit pembuat (BR-26), atau menolak dengan alasan.
 *
 * Unit pembuat = pilihan kasubag, bila tidak dipilih = unit bawaan jenis permohonan. Dengan unit: dibuat penugasan
 * (disposisi + penerima untuk setiap petugas aktif unit) dan status menjadi `pembuatan`. Tanpa unit sama sekali
 * (jenis tanpa unit bawaan dan kasubag tidak memilih): jalur lama, langsung `penerbitan` oleh admin persuratan.
 */
class RekomendasiKasubag
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $kasubag, ?string $catatan = null, ?string $unitPembuatId = null): Permohonan
    {
        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($kasubag, $catatan, $unitPembuatId) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::RekomendasiKasubag]);
            Gate::forUser($kasubag)->authorize('rekomendasiKasubag', $segar);

            $unitId = $unitPembuatId ?? $segar->jenis->unit_pembuat_bawaan_id;

            if ($unitId === null) {
                $this->transisi->ke($segar, StatusPermohonan::Penerbitan, $kasubag, $catatan ?: 'Direkomendasikan kasubag');

                return $segar;
            }

            // Unit dikunci agar perubahan petugas/aktif oleh super-admin tidak menyelinap di tengah penugasan.
            $unit = UnitPembuat::whereKey($unitId)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['unit_pembuat_id' => 'Unit pembuat tidak ditemukan.']);

            if (! $unit->aktif) {
                throw ValidationException::withMessages(['unit_pembuat_id' => "Unit pembuat {$unit->nama} tidak aktif."]);
            }

            $petugas = $unit->petugasAktif()->get();

            if ($petugas->isEmpty()) {
                throw ValidationException::withMessages(['unit_pembuat_id' => "Unit pembuat {$unit->nama} belum memiliki petugas aktif."]);
            }

            $segar->forceFill(['unit_pembuat_id' => $unit->getKey()])->save();

            $disposisi = Disposisi::create([
                'permohonan_id' => $segar->getKey(),
                'dari_user_id' => $kasubag->getKey(),
                'dari_jabatan_id' => $kasubag->jabatanAktif()->first()?->getKey(),
                'instruksi' => ['tindak_lanjuti'],
                'catatan' => $catatan ?: "Buat surat untuk permohonan {$segar->nomor}",
                'batas_waktu' => now()->addDays(7),
                'sifat' => 'biasa',
            ]);

            foreach ($petugas as $orang) {
                DisposisiPenerima::create(['disposisi_id' => $disposisi->getKey(), 'user_id' => $orang->getKey()]);
            }

            $ringkas = "Direkomendasikan {$kasubag->name}; ditugaskan ke unit {$unit->nama} (petugas: {$petugas->pluck('name')->implode(', ')})";
            $this->transisi->ke($segar, StatusPermohonan::Pembuatan, $kasubag, $catatan ? "{$ringkas}. Catatan: {$catatan}" : $ringkas);

            return $segar;
        });
    }

    public function tolak(Permohonan $permohonan, User $kasubag, string $alasan): Permohonan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($kasubag, $alasan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::RekomendasiKasubag]);
            Gate::forUser($kasubag)->authorize('rekomendasiKasubag', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Ditolak, $kasubag, $alasan);

            return $segar;
        });
    }
}
