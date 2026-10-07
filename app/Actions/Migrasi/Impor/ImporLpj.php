<?php

namespace App\Actions\Migrasi\Impor;

use App\Actions\Lpj\HitungNilaiAkhir;
use App\Contracts\PenyimpananBerkas;
use App\Models\ImporLog;
use App\Models\Jabatan;
use App\Models\Lpj;
use App\Models\NilaiLpj;
use App\Models\Permohonan;
use App\Models\RubrikLpj;
use App\Rules\TautanMediaValid;
use App\Support\Migrasi\Bantu;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\PenguraiTanggal;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use App\Support\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Laporan → lpj + nilai_lpj (§6): penilai = pemangku jabatan pada tanggal pengajuan; nilai akhir dihitung bila semua lengkap. */
class ImporLpj
{
    private const KODE_RUBRIK = ['ketepatan' => 'ketepatan', 'kepatuhan' => 'kepatuhan', 'kelengkapan' => 'kelengkapan', 'fakultas' => 'kontribusi_fakultas', 'kontribusi_fakultas' => 'kontribusi_fakultas'];

    /** @param  list<array<string, mixed>>  $baris */
    public function jalankan(KonteksImpor $k, array $baris): void
    {
        foreach ($baris as $r) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            if (PolaContoh::barisContoh($r)) {
                $k->catat('Laporan', $id, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            if ($baru = $k->sebelumnya('Laporan', $id)) {
                $k->catat('Laporan', $id, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', 'lpj', $baru);

                continue;
            }

            try {
                [$lpj, $peringatan] = DB::transaction(fn () => $this->buat($k, $r, $id));
                $k->catat('Laporan', $id, $peringatan === [] ? ImporLog::OK : ImporLog::PERINGATAN, $peringatan === [] ? null : implode(' ', $peringatan), 'lpj', $lpj->getKey());
            } catch (Throwable $e) {
                $k->catat('Laporan', $id, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{0: Lpj, 1: list<string>}
     */
    private function buat(KonteksImpor $k, array $r, string $id): array
    {
        $peringatan = [];
        $requestId = Sel::teks($r['requestId'] ?? null) ?? throw new RuntimeException('requestId kosong.');
        $permohonanId = $k->peta['permohonan'][$requestId] ?? $k->sebelumnya('Requests', $requestId) ?? Permohonan::where('nomor_lama', $requestId)->value('id');
        $permohonan = $permohonanId !== null ? Permohonan::find($permohonanId) : null;

        if ($permohonan === null) {
            throw new RuntimeException("Permohonan '{$requestId}' tidak ditemukan.");
        }

        if (Lpj::where('permohonan_id', $permohonan->getKey())->exists()) {
            throw new RuntimeException("Permohonan '{$requestId}' sudah memiliki LPJ.");
        }

        $diajukan = PenguraiTanggal::urai($r['submittedAt'] ?? null) ?? $permohonan->tanggal_selesai;
        $lpj = new Lpj([
            'tanggal_pelaksanaan' => PenguraiTanggal::urai($r['tanggalPelaksanaan'] ?? null)?->toDateString() ?? $permohonan->tanggal_mulai->toDateString(),
            'jumlah_peserta' => Sel::int($r['jumlahPeserta'] ?? null) ?? 0,
            'ringkasan' => Sel::teks($r['ringkasan'] ?? null), 'kendala' => Sel::teks($r['kendala'] ?? null),
            'solusi' => Sel::teks($r['solusi'] ?? null), 'rekomendasi' => Sel::teks($r['rekomendasi'] ?? null),
        ]);

        foreach (['igUrl' => 'tautan_instagram', 'videoUrl' => 'tautan_video'] as $kolom => $atribut) {
            $url = Sel::teks($r[$kolom] ?? null);

            if ($url === null) {
                continue;
            }

            Validator::make(['u' => $url], ['u' => [new TautanMediaValid]])->passes() && ! PolaContoh::kolomContoh($url)
                ? $lpj->{$atribut} = $url
                : $peringatan[] = "Tautan {$atribut} dibuang (bukan Instagram/YouTube sah).";
        }

        $lpj->forceFill([
            'permohonan_id' => $permohonan->getKey(), 'status' => Lpj::DIAJUKAN, 'diajukan_pada' => $diajukan,
            'batas_waktu' => $permohonan->tanggal_selesai->addDays((int) Pengaturan::get('batas_hari_lpj'))->toDateString(), 'sumber_id_lama' => $id,
        ])->save();

        $url = Sel::teks($r['fileUrl'] ?? $r['file'] ?? null);

        if ($url !== null) {
            Bantu::urlBerkas($url) !== null
                ? app(PenyimpananBerkas::class)->simpan($lpj, 'lpj', $url, 'Berkas LPJ', null)
                : $peringatan[] = 'Tautan berkas LPJ dibuang (tidak memenuhi kebijakan berkas).';
        } else {
            $peringatan[] = 'LPJ lama tanpa tautan berkas.';
        }

        $this->nilai($k, $lpj, Sel::json($r['penilaian'] ?? null), $diajukan, $peringatan);
        app(HitungNilaiAkhir::class)->jalankan($lpj);

        return [$lpj, $peringatan];
    }

    /** @param  list<string>  $peringatan */
    private function nilai(KonteksImpor $k, Lpj $lpj, mixed $penilaian, CarbonInterface $tanggal, array &$peringatan): void
    {
        if (! is_array($penilaian)) {
            return;
        }

        foreach ($penilaian as $peranLama => $aspek) {
            $kodeJabatan = match (strtolower((string) $peranLama)) {
                'dekan' => 'dekan', 'kasubag', 'kabag_umum' => 'kasubag-umum', 'wd1', 'wd2' => $k->pemetaan->jabatanWd((string) $peranLama), default => null,
            };
            $jabatan = $kodeJabatan !== null ? Jabatan::firstWhere('kode', $kodeJabatan) : null;

            if ($jabatan === null || ! is_array($aspek)) {
                $peringatan[] = "Penilai '{$peranLama}' tidak terpetakan; nilainya dilewati.";

                continue;
            }

            $user = Bantu::pemangku($kodeJabatan, $tanggal) ?? $k->pelaksana;

            foreach ($aspek as $aspekLama => $nilai) {
                $kodeRubrik = self::KODE_RUBRIK[strtolower((string) $aspekLama)] ?? null;
                $rubrik = $kodeRubrik !== null ? RubrikLpj::firstWhere('kode', $kodeRubrik) : null;

                if ($nilai === null || $nilai === '' || $rubrik === null) {
                    $nilai !== null && $nilai !== '' ? $peringatan[] = "Aspek '{$aspekLama}' tidak punya rubrik; dilewati." : null;

                    continue;
                }

                if (! is_numeric($nilai) || (float) $nilai < 0 || (float) $nilai > $rubrik->nilai_maks) {
                    $peringatan[] = "Nilai {$peranLama}/{$aspekLama} ({$nilai}) di luar 0..{$rubrik->nilai_maks}; dilewati.";

                    continue;
                }

                if (! in_array($kodeJabatan, $rubrik->penilai_jabatan, true)) {
                    $peringatan[] = "{$peranLama} bukan penilai rubrik {$kodeRubrik}; nilai dilewati.";

                    continue;
                }

                NilaiLpj::create(['lpj_id' => $lpj->getKey(), 'rubrik_lpj_id' => $rubrik->getKey(), 'penilai_jabatan_id' => $jabatan->getKey(), 'penilai_user_id' => $user?->getKey(), 'nilai' => (float) $nilai]);
            }
        }
    }
}
