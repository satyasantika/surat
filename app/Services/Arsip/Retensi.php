<?php

namespace App\Services\Arsip;

use App\Enums\StatusNaskah;
use App\Enums\StatusSuratMasuk;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\SuratMasuk;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Peninjauan retensi arsip (RS-09, LAP-06). Arsip = surat masuk berstatus diarsipkan dan naskah terbit yang
 * berklasifikasi arsip. Tanggal dasar: tanggal diarsipkan (surat masuk) atau tanggal naskah. Hanya meninjau,
 * tidak pernah menghapus: pemusnahan dilakukan lewat prosedur resmi dan dicatat manual dengan berita acara.
 */
class Retensi
{
    public const AKTIF_LEWAT = 'lewat_aktif';

    public const INAKTIF_LEWAT = 'lewat_inaktif';

    /**
     * @return list<array{jenis: string, nomor: string, tanggal: string, klasifikasi: string, batas_aktif: string, batas_inaktif: ?string, tahap: string, nasib_akhir: ?string}>
     */
    public static function tinjau(?CarbonInterface $acuan = null): array
    {
        $acuan = CarbonImmutable::instance($acuan ?? now())->startOfDay();
        $baris = [];

        $tambah = function (string $jenis, string $nomor, CarbonInterface $tanggal, KlasifikasiArsip $k) use (&$baris, $acuan) {
            if ($k->retensi_aktif_tahun === null) {
                return;
            }

            $batasAktif = CarbonImmutable::instance($tanggal)->startOfDay()->addYears($k->retensi_aktif_tahun);
            $batasInaktif = $k->retensi_inaktif_tahun !== null ? $batasAktif->addYears($k->retensi_inaktif_tahun) : null;

            $tahap = match (true) {
                $batasInaktif !== null && $acuan->gte($batasInaktif) => self::INAKTIF_LEWAT,
                $acuan->gte($batasAktif) => self::AKTIF_LEWAT,
                default => null,
            };

            if ($tahap === null) {
                return;
            }

            $baris[] = [
                'jenis' => $jenis, 'nomor' => $nomor, 'tanggal' => $tanggal->format('Y-m-d'), 'klasifikasi' => "{$k->kode} {$k->nama}",
                'batas_aktif' => $batasAktif->toDateString(), 'batas_inaktif' => $batasInaktif?->toDateString(), 'tahap' => $tahap,
                'nasib_akhir' => $tahap === self::INAKTIF_LEWAT ? (KlasifikasiArsip::KETERANGAN_AKHIR[$k->keterangan_akhir] ?? null) : null,
            ];
        };

        SuratMasuk::where('status', StatusSuratMasuk::Diarsipkan->value)->whereNotNull('klasifikasi_arsip_id')->with('klasifikasiArsip')->orderBy('id')
            ->each(fn (SuratMasuk $s) => $tambah('Surat masuk', (string) $s->nomor_agenda, $s->diarsipkan_pada ?? $s->tanggal_terima, $s->klasifikasiArsip));

        Naskah::where('status', StatusNaskah::Terbit->value)->whereNotNull('klasifikasi_arsip_id')->whereNotNull('tanggal_naskah')->with('klasifikasiArsip')->orderBy('id')
            ->each(fn (Naskah $n) => $tambah('Naskah keluar', (string) $n->nomor, $n->tanggal_naskah, $n->klasifikasiArsip));

        usort($baris, fn ($a, $b) => [$a['klasifikasi'], $a['tahap'], $a['nomor']] <=> [$b['klasifikasi'], $b['tahap'], $b['nomor']]);

        return $baris;
    }

    public static function labelTahap(string $tahap): string
    {
        return $tahap === self::INAKTIF_LEWAT ? 'Melewati retensi inaktif' : 'Melewati retensi aktif (masuk masa inaktif)';
    }
}
