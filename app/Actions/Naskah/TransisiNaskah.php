<?php

namespace App\Actions\Naskah;

use App\Enums\StatusNaskah;
use App\Models\Naskah;
use App\Models\RiwayatNaskah;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Semua perubahan status naskah lewat sini (BR-06): kunci per naskah mencegah klik ganda, naskah dibaca ulang
 * dengan FOR UPDATE di dalam transaksi, dan setiap transisi menulis riwayat_naskah.
 */
class TransisiNaskah
{
    /**
     * @template T
     *
     * @param  Closure(Naskah): T  $kerja  menerima naskah segar (terkunci); jalankan perubahan di dalamnya
     * @return T
     */
    public function dalamKunci(Naskah $naskah, Closure $kerja): mixed
    {
        return Cache::lock("naskah:{$naskah->getKey()}:transisi", 10)->block(5, fn () => DB::transaction(
            fn () => $kerja(Naskah::whereKey($naskah->getKey())->lockForUpdate()->firstOrFail())
        ));
    }

    /** @param  list<StatusNaskah>  $diizinkan */
    public function pastikanStatus(Naskah $naskah, array $diizinkan, string $pesan = 'Status naskah tidak memungkinkan aksi ini.'): void
    {
        if (! in_array($naskah->status, $diizinkan, true)) {
            throw ValidationException::withMessages(['status' => $pesan]);
        }
    }

    public function ke(Naskah $naskah, StatusNaskah $ke, User $oleh, ?string $catatan = null): void
    {
        $dari = $naskah->status;
        $naskah->status = $ke;
        $naskah->save();

        RiwayatNaskah::create([
            'naskah_id' => $naskah->getKey(),
            'dari_status' => $dari->value,
            'ke_status' => $ke->value,
            'oleh' => $oleh->getKey(),
            'catatan' => $catatan,
        ]);
    }
}
