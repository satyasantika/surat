<?php

namespace App\Actions\Permohonan;

use App\Actions\Naskah\SimpanDraf;
use App\Enums\StatusPermohonan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\User;
use App\Services\Ruangan\KetersediaanRuangan;
use App\Support\KunciRuangan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Penerbitan surat izin (BR-14): setelah rekomendasi, admin membuat draf naskah dari templat jenis permohonan
 * dengan variabel terisi otomatis. Bentrok ruangan dicek ulang di dalam kunci sebelum draf dibuat. Selanjutnya
 * naskah mengikuti alur F5 (paraf, tanda tangan, nomor, terbit).
 */
class TerbitkanIzin
{
    public function __construct(
        private readonly TransisiPermohonan $transisi,
        private readonly SimpanDraf $draf,
        private readonly KetersediaanRuangan $ketersediaan,
    ) {}

    public function jalankan(Permohonan $permohonan, User $admin): Naskah
    {
        $butir = $permohonan->ruangan()->where('status', PermohonanRuangan::DITAHAN)->get()
            ->map(fn (PermohonanRuangan $r) => ['kode' => $r->kode_ruangan, 'tanggal' => $r->tanggal->toDateString()])->all();

        return KunciRuangan::dengan($butir, fn () => $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($admin) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::Penerbitan]);
            Gate::forUser($admin)->authorize('terbitkanIzin', $segar);

            if ($segar->naskah_izin_id !== null && Naskah::whereKey($segar->naskah_izin_id)->where('status', '!=', 'dibatalkan')->exists()) {
                throw ValidationException::withMessages(['naskah' => 'Surat izin untuk permohonan ini sudah dibuat.']);
            }

            $this->cekUlangBentrok($segar);

            $segar->loadMissing(['ormawa', 'jenis.jenisNaskah', 'ruangan']);
            $jenisNaskah = $segar->jenis->jenisNaskah ?? throw ValidationException::withMessages(['jenis' => 'Jenis permohonan belum memiliki templat surat.']);

            $naskah = $this->draf->jalankan(null, [
                'jenis_naskah_id' => $jenisNaskah->getKey(),
                'klasifikasi_arsip_id' => KlasifikasiArsip::where('kode', 'KM.03.02')->value('id'),
                'klasifikasi_keamanan' => 'biasa',
                'derajat_kecepatan' => 'biasa',
                'perihal' => $segar->perihal,
                'penanda_tangan_jabatan_id' => $jenisNaskah->jabatan_penanda_tangan_bawaan_id,
                'mode_tanda_tangan' => $jenisNaskah->mode_tanda_tangan_diizinkan[0],
                'data' => $this->isiOtomatis($segar, $jenisNaskah),
                'tujuan' => [['nama' => "Ketua {$segar->ormawa->nama}"]],
                'isi' => null,
            ], $admin);

            $naskah->forceFill(['permohonan_id' => $segar->getKey()])->save();
            $segar->forceFill(['naskah_izin_id' => $naskah->getKey()])->save();

            activity('permohonan')->event('draf-izin')->performedOn($segar)->withProperties(['naskah' => $naskah->getKey()])->log('Draf surat izin dibuat');

            return $naskah;
        }));
    }

    /** @throws ValidationException bila ruangan sudah dipakai oleh pihak lain */
    private function cekUlangBentrok(Permohonan $permohonan): void
    {
        $bentrok = [];

        foreach ($permohonan->ruangan()->where('status', PermohonanRuangan::DITAHAN)->get() as $r) {
            if ($this->ketersediaan->terpakai($r->kode_ruangan, $r->tanggal->toDateString(), $r->sesi, $permohonan->getKey())) {
                $bentrok[] = "{$r->nama_ruangan} {$r->tanggal->locale('id')->translatedFormat('d F Y')} sesi {$r->sesi}";
            }
        }

        if ($bentrok !== []) {
            throw ValidationException::withMessages(['ruangan' => 'Ruangan kini sudah dipakai: '.implode('; ', $bentrok).'. Penerbitan ditolak.']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function isiOtomatis(Permohonan $p, JenisNaskah $jenisNaskah): array
    {
        $tempat = $p->ruangan->isNotEmpty()
            ? $p->ruangan->map(fn (PermohonanRuangan $r) => "{$r->nama_ruangan} ({$r->sesi}, ".CarbonImmutable::parse($r->tanggal)->locale('id')->translatedFormat('d F Y').')')->implode('; ')
            : (string) $p->tempat_lain;

        $nilai = [
            'ormawa' => $p->ormawa->nama,
            'nama_kegiatan' => $p->nama_kegiatan,
            'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
            'tempat' => $tempat !== '' ? $tempat : '-',
            'penanggung_jawab' => (string) ($p->penanggung_jawab['ketua']['nama'] ?? ''),
            'tujuan' => "Ketua {$p->ormawa->nama}",
        ];

        // Hanya kunci yang didefinisikan templat; variabel wajib yang tak terisi diberi strip.
        $hasil = [];

        foreach ($jenisNaskah->variabel as $v) {
            $hasil[$v['kunci']] = $nilai[$v['kunci']] ?? (($v['wajib'] ?? false) ? '-' : '');
        }

        return $hasil;
    }
}
