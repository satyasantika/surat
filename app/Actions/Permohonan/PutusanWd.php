<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusPermohonan;
use App\Models\DisposisiPenerima;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Putusan Wakil Dekan tujuan disposisi (pemangku jabatan, termasuk Plt): semua setuju → rekomendasi kasubag;
 * satu tolak → permohonan ditolak dan ruangan dilepas (BR-14).
 */
class PutusanWd
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    public function jalankan(Permohonan $permohonan, User $pelaku, string $putusan, ?string $catatan = null): Permohonan
    {
        if (! in_array($putusan, [PersetujuanWd::SETUJU, PersetujuanWd::TOLAK], true)) {
            throw ValidationException::withMessages(['putusan' => 'Putusan harus setuju atau tolak.']);
        }

        $catatan = $catatan !== null ? trim($catatan) : null;

        if ($putusan === PersetujuanWd::TOLAK && blank($catatan)) {
            throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi saat menolak.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $putusan, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::PersetujuanWd]);
            Gate::forUser($pelaku)->authorize('putusWd', $segar);

            $milikSaya = $segar->persetujuanWd()->where('putusan', PersetujuanWd::MENUNGGU)
                ->whereIn('jabatan_id', $pelaku->jabatanAktif()->pluck('id'))->get();

            foreach ($milikSaya as $baris) {
                $baris->update(['putusan' => $putusan, 'user_id' => $pelaku->getKey(), 'catatan' => $catatan, 'diputus_pada' => now()]);
            }

            // Disposisi pribadi pemutus dianggap selesai (laporan = putusan).
            DisposisiPenerima::where('user_id', $pelaku->getKey())->whereHas('disposisi', fn ($q) => $q->where('permohonan_id', $segar->getKey()))
                ->where('status', '!=', StatusDisposisiPenerima::Selesai->value)
                ->update([
                    'status' => StatusDisposisiPenerima::Selesai->value, 'laporan_tindak_lanjut' => ucfirst($putusan).($catatan ? ": {$catatan}" : ''),
                    'dibaca_pada' => now(), 'ditindaklanjuti_pada' => now(), 'selesai_pada' => now(),
                ]);

            if ($putusan === PersetujuanWd::TOLAK) {
                $this->transisi->ke($segar, StatusPermohonan::Ditolak, $pelaku, "Ditolak WD: {$catatan}");
            } elseif (! $segar->persetujuanWd()->where('putusan', '!=', PersetujuanWd::SETUJU)->exists()) {
                $this->transisi->ke($segar, StatusPermohonan::RekomendasiKasubag, $pelaku, 'Seluruh Wakil Dekan menyetujui');
            } else {
                activity('permohonan')->event('putusan-wd')->performedOn($segar)->withProperties(['putusan' => $putusan])->log('Putusan WD dicatat');
            }

            return $segar;
        });
    }
}
