<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusPermohonan;
use App\Models\DisposisiPenerima;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Putusan Wakil Dekan tujuan disposisi (pemangku jabatan, termasuk Plt), menurut mode pilihan dekan (BR-14/BR-25):
 * - `semua` (bawaan): semua setuju → rekomendasi kasubag; satu tolak → permohonan ditolak dan ruangan dilepas.
 * - `salah_satu`: persetujuan pertama menutup tahap (WD lain diabaikan); penolakan hanya berlaku bila semua WD tujuan menolak.
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
            $this->selesaikanDisposisi(
                DisposisiPenerima::where('user_id', $pelaku->getKey()),
                $segar,
                ucfirst($putusan).($catatan ? ": {$catatan}" : ''),
            );

            $salahSatu = $segar->modeWd() === Permohonan::MODE_WD_SALAH_SATU;
            $masihMenunggu = $segar->persetujuanWd()->where('putusan', PersetujuanWd::MENUNGGU)->exists();

            if ($putusan === PersetujuanWd::TOLAK) {
                // Mode salah_satu: satu penolakan tidak mengakhiri permohonan selama WD lain masih dapat menyetujui.
                if ($salahSatu && $masihMenunggu) {
                    activity('permohonan')->event('putusan-wd')->performedOn($segar)->withProperties(['putusan' => $putusan, 'mode' => 'salah_satu'])->log('Penolakan WD dicatat; WD lain masih dapat menyetujui');
                } else {
                    $this->transisi->ke($segar, StatusPermohonan::Ditolak, $pelaku, $salahSatu ? "Seluruh WD tujuan menolak; terakhir: {$catatan}" : "Ditolak WD: {$catatan}");
                }
            } elseif ($salahSatu) {
                $diabaikan = $this->abaikanSisa($segar, $pelaku);
                $this->transisi->ke($segar, StatusPermohonan::RekomendasiKasubag, $pelaku, "Disetujui {$pelaku->name} (cukup salah satu WD)"
                    .($diabaikan !== [] ? '; WD lain diabaikan: '.implode(', ', $diabaikan) : ''));
            } elseif (! $segar->persetujuanWd()->where('putusan', '!=', PersetujuanWd::SETUJU)->exists()) {
                $this->transisi->ke($segar, StatusPermohonan::RekomendasiKasubag, $pelaku, 'Seluruh Wakil Dekan menyetujui');
            } else {
                activity('permohonan')->event('putusan-wd')->performedOn($segar)->withProperties(['putusan' => $putusan])->log('Putusan WD dicatat');
            }

            return $segar;
        });
    }

    /**
     * Mode salah_satu: baris persetujuan yang masih menunggu ditandai diabaikan dan disposisi pribadi WD lainnya
     * diselesaikan otomatis dengan catatan. Mengembalikan nama jabatan yang diabaikan (untuk riwayat).
     *
     * @return list<string>
     */
    private function abaikanSisa(Permohonan $permohonan, User $pemutus): array
    {
        $sisa = $permohonan->persetujuanWd()->where('putusan', PersetujuanWd::MENUNGGU)->with('jabatan')->get();

        if ($sisa->isEmpty()) {
            return [];
        }

        $alasan = "Diabaikan: {$pemutus->name} telah menyetujui (mode cukup salah satu WD)";

        foreach ($sisa as $baris) {
            $baris->update(['putusan' => PersetujuanWd::DIABAIKAN, 'catatan' => $alasan, 'diputus_pada' => now()]);
        }

        $pengguna = $this->disposisiBelumSelesai(DisposisiPenerima::whereIn('jabatan_id', $sisa->pluck('jabatan_id')), $permohonan)
            ->with('user')->get()->pluck('user')->filter()->all();
        $this->selesaikanDisposisi(DisposisiPenerima::whereIn('jabatan_id', $sisa->pluck('jabatan_id')), $permohonan, $alasan);

        app(NotifikasiAlur::class)->disposisiWdDiabaikan($permohonan, $pengguna, $pemutus);

        return $sisa->map(fn (PersetujuanWd $b) => $b->jabatan->nama)->all();
    }

    /**
     * @param  Builder<DisposisiPenerima>  $penerima
     */
    private function selesaikanDisposisi(Builder $penerima, Permohonan $permohonan, string $laporan): void
    {
        $this->disposisiBelumSelesai($penerima, $permohonan)->update([
            'status' => StatusDisposisiPenerima::Selesai->value, 'laporan_tindak_lanjut' => $laporan,
            'dibaca_pada' => now(), 'ditindaklanjuti_pada' => now(), 'selesai_pada' => now(),
        ]);
    }

    /**
     * @param  Builder<DisposisiPenerima>  $penerima
     * @return Builder<DisposisiPenerima>
     */
    private function disposisiBelumSelesai(Builder $penerima, Permohonan $permohonan): Builder
    {
        return $penerima->whereHas('disposisi', fn ($q) => $q->where('permohonan_id', $permohonan->getKey()))
            ->where('status', '!=', StatusDisposisiPenerima::Selesai->value);
    }
}
