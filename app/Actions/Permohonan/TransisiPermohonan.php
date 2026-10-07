<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RiwayatPermohonan;
use App\Models\User;
use App\Support\AlurPermohonan;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Semua perubahan status permohonan lewat sini: kunci per permohonan (anti klik ganda), baca ulang
 * FOR UPDATE di dalam transaksi, validasi peta AlurPermohonan, dan tulis riwayat_permohonan.
 */
class TransisiPermohonan
{
    /**
     * @template T
     *
     * @param  Closure(Permohonan): T  $kerja
     * @return T
     */
    public function dalamKunci(Permohonan $permohonan, Closure $kerja): mixed
    {
        return Cache::lock("permohonan:{$permohonan->getKey()}:transisi", 10)->block(5, fn () => DB::transaction(
            fn () => $kerja(Permohonan::whereKey($permohonan->getKey())->lockForUpdate()->firstOrFail())
        ));
    }

    /** @param  list<StatusPermohonan>  $diizinkan */
    public function pastikanStatus(Permohonan $permohonan, array $diizinkan): void
    {
        if (! in_array($permohonan->status, $diizinkan, true)) {
            throw ValidationException::withMessages(['status' => 'Status permohonan ('.$permohonan->status->label().') tidak memungkinkan aksi ini.']);
        }
    }

    public function ke(Permohonan $permohonan, StatusPermohonan $ke, User $oleh, ?string $catatan = null): void
    {
        $dari = $permohonan->status;
        AlurPermohonan::pastikan($dari, $ke);

        $permohonan->status = $ke;

        if ($ke === StatusPermohonan::Selesai) {
            $permohonan->selesai_pada = now();
        }

        $permohonan->save();

        RiwayatPermohonan::create([
            'permohonan_id' => $permohonan->getKey(), 'dari_status' => $dari->value, 'ke_status' => $ke->value,
            'oleh' => $oleh->getKey(), 'catatan' => $catatan,
        ]);

        if (! AlurPermohonan::menahanRuangan($ke)) {
            $this->lepasRuangan($permohonan);
        }
    }

    /** Ruangan yang ditahan dilepas (ditolak/dibatalkan); yang sudah dikonfirmasi tidak disentuh. */
    public function lepasRuangan(Permohonan $permohonan): int
    {
        return PermohonanRuangan::where('permohonan_id', $permohonan->getKey())
            ->where('status', PermohonanRuangan::DITAHAN)
            ->update(['status' => PermohonanRuangan::DILEPAS]);
    }
}
