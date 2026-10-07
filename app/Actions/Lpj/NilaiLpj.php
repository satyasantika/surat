<?php

namespace App\Actions\Lpj;

use App\Models\Jabatan;
use App\Models\Lpj;
use App\Models\NilaiLpj as Nilai;
use App\Models\RubrikLpj;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Penilaian LPJ oleh pemangku jabatan penilai (BR-17). Nilai 0..maks per rubrik; dapat direvisi penilai yang
 * sama selama LPJ belum final. Setelah semua penilai lengkap → nilai akhir dan status dinilai.
 */
class NilaiLpj
{
    public function __construct(private readonly HitungNilaiAkhir $hitung) {}

    /**
     * @param  array<string, array{nilai: mixed, catatan?: ?string}>  $isian  kunci = kode rubrik
     * @return array{nilai_akhir: ?string, lengkap: bool}
     */
    public function jalankan(Lpj $lpj, User $penilai, array $isian): array
    {
        Gate::forUser($penilai)->authorize('nilai', $lpj);

        if ($isian === []) {
            throw ValidationException::withMessages(['nilai' => 'Isi minimal satu nilai.']);
        }

        return Cache::lock("lpj:{$lpj->getKey()}:nilai", 10)->block(5, fn () => DB::transaction(function () use ($lpj, $penilai, $isian) {
            $segar = Lpj::whereKey($lpj->getKey())->lockForUpdate()->firstOrFail();

            if ($segar->status !== Lpj::DIAJUKAN) {
                throw ValidationException::withMessages(['status' => 'LPJ ini tidak sedang menunggu penilaian.']);
            }

            $jabatanKu = $penilai->jabatanAktif()->keyBy('kode');

            foreach ($isian as $kodeRubrik => $baris) {
                $rubrik = RubrikLpj::where('kode', $kodeRubrik)->where('aktif', true)->first();

                if ($rubrik === null) {
                    throw ValidationException::withMessages(["nilai.{$kodeRubrik}" => 'Rubrik tidak dikenal.']);
                }

                /** @var Jabatan|null $jabatan */
                $jabatan = collect($rubrik->penilai_jabatan)->map(fn (string $k) => $jabatanKu->get($k))->filter()->first();

                if ($jabatan === null) {
                    throw ValidationException::withMessages(["nilai.{$kodeRubrik}" => "Anda bukan penilai untuk rubrik {$rubrik->nama}."]);
                }

                $nilai = $baris['nilai'] ?? null;

                if (! is_numeric($nilai) || $nilai < 0 || $nilai > $rubrik->nilai_maks) {
                    throw ValidationException::withMessages(["nilai.{$kodeRubrik}" => "Nilai {$rubrik->nama} harus 0–{$rubrik->nilai_maks}."]);
                }

                Nilai::updateOrCreate(
                    ['lpj_id' => $segar->getKey(), 'rubrik_lpj_id' => $rubrik->getKey(), 'penilai_jabatan_id' => $jabatan->getKey()],
                    ['penilai_user_id' => $penilai->getKey(), 'nilai' => round((float) $nilai, 2), 'catatan' => filled($baris['catatan'] ?? null) ? mb_substr((string) $baris['catatan'], 0, 2000) : null],
                );
            }

            $lengkap = $this->hitung->jalankan($segar);

            return ['nilai_akhir' => $segar->fresh()->nilai_akhir, 'lengkap' => $lengkap];
        }));
    }

    /**
     * Rubrik yang boleh dinilai pengguna pada saat ini.
     *
     * @return Collection<int, RubrikLpj>
     */
    public static function rubrikUntuk(User $penilai): Collection
    {
        $kode = $penilai->jabatanAktif()->pluck('kode')->all();

        return RubrikLpj::where('aktif', true)->orderBy('urutan')->get()
            ->filter(fn (RubrikLpj $r) => array_intersect($r->penilai_jabatan, $kode) !== [])->values();
    }
}
