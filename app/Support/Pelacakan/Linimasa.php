<?php

namespace App\Support\Pelacakan;

use App\Enums\StatusPermohonan;
use App\Enums\StatusSuratMasuk;
use App\Models\Aktivitas;
use App\Models\Disposisi;
use App\Models\Naskah;
use App\Models\PemangkuJabatan;
use App\Models\Permohonan;
use App\Models\RiwayatNaskah;
use App\Models\SuratMasuk;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Linimasa terpadu (BR-27): satu sumber tampilan perjalanan permohonan maupun surat masuk bagi pengirim (akun ormawa)
 * dan halaman lacak publik. Menggabungkan riwayat_permohonan, riwayat naskah, disposisi, dan jejak aktivitas surat
 * masuk menjadi daftar tahap berurutan waktu.
 *
 * Pelaku dinyatakan pada tingkat JABATAN (bukan nama pribadi). Catatan bebas petugas, instruksi disposisi, dan
 * kontak tidak pernah dimuat; satu-satunya teks yang diteruskan adalah alasan yang memang ditujukan kepada pengirim
 * (dikembalikan, ditolak, dibatalkan).
 *
 * @phpstan-type Tahap array{waktu: CarbonInterface, tahap: string, pelaku: ?string, catatan: ?string}
 */
class Linimasa
{
    private const LABEL_PERAN = [
        'pengurus-ormawa' => 'Pengurus ormawa',
        'pembina-ormawa' => 'Dosen pembina ormawa',
        'admin-persuratan' => 'Admin persuratan',
        'staf-khusus' => 'Staf khusus dekanat',
        'operator-layanan' => 'Operator layanan',
        'pegawai' => 'Pegawai fakultas',
    ];

    /** @var array<string, ?string> */
    private array $tembolokJabatan = [];

    /** @return list<Tahap> */
    public function permohonan(Permohonan $permohonan): array
    {
        $tahap = [];

        foreach ($permohonan->riwayat()->with('pelaku.roles')->get() as $r) {
            $ke = StatusPermohonan::from($r->ke_status);
            $tahap[] = [
                'waktu' => $r->created_at,
                'tahap' => $ke->labelPublik(),
                'pelaku' => $this->jabatanPelaku($r->pelaku, $r->created_at),
                // Alasan hanya untuk keputusan yang ditujukan kepada pengirim; catatan internal tidak.
                'catatan' => in_array($ke, [StatusPermohonan::Dikembalikan, StatusPermohonan::Ditolak, StatusPermohonan::Dibatalkan], true) ? $r->catatan : null,
            ];
        }

        if ($permohonan->naskah_izin_id !== null) {
            $labelNaskah = ['paraf' => 'Surat izin dalam proses paraf', 'menunggu_tanda_tangan' => 'Surat izin menunggu tanda tangan', 'terbit' => 'Surat izin terbit'];

            foreach (RiwayatNaskah::where('naskah_id', $permohonan->naskah_izin_id)->whereIn('ke_status', array_keys($labelNaskah))->with('pelaku.roles')->orderBy('created_at')->get() as $r) {
                $tahap[] = ['waktu' => $r->created_at, 'tahap' => $labelNaskah[$r->ke_status], 'pelaku' => $this->jabatanPelaku($r->pelaku, $r->created_at), 'catatan' => null];
            }
        }

        if ($permohonan->hasil_dikirim_pada !== null) {
            $tahap[] = ['waktu' => $permohonan->hasil_dikirim_pada, 'tahap' => 'Hasil dikirim kepada pengirim', 'pelaku' => $this->jabatanPelaku($permohonan->hasil_dikirim_oleh ? User::with('roles')->find($permohonan->hasil_dikirim_oleh) : null, $permohonan->hasil_dikirim_pada), 'catatan' => null];
        }

        return $this->urutkan($tahap);
    }

    /** @return list<Tahap> */
    public function suratMasuk(SuratMasuk $surat): array
    {
        $tahap = [];

        $tahap[] = ['waktu' => $surat->tanggal_terima, 'tahap' => 'Surat diterima dan didaftarkan', 'pelaku' => $this->jabatanPelaku($surat->diregistrasi_oleh ? User::with('roles')->find($surat->diregistrasi_oleh) : null, $surat->tanggal_terima), 'catatan' => null];

        if ($surat->diteruskan_pada !== null) {
            $tahap[] = ['waktu' => $surat->diteruskan_pada, 'tahap' => 'Diteruskan kepada dekan', 'pelaku' => $this->jabatanPelaku($surat->diteruskan_oleh ? User::with('roles')->find($surat->diteruskan_oleh) : null, $surat->diteruskan_pada), 'catatan' => null];
        }

        // Disposisi yang merupakan penugasan pembuat dikenali dari jejak aktivitas (event tugaskan-pembuat).
        $penugasan = Aktivitas::where('subject_type', $surat->getMorphClass())->where('subject_id', $surat->getKey())->where('event', 'tugaskan-pembuat')->get()
            ->mapWithKeys(fn (Aktivitas $a) => [(string) $a->properties->get('disposisi_id') => (string) $a->properties->get('unit')]);

        foreach (Disposisi::where('surat_masuk_id', $surat->getKey())->with(['dari.roles', 'dariJabatan'])->orderBy('created_at')->get() as $d) {
            $unit = $penugasan->get($d->getKey());
            $tahap[] = [
                'waktu' => $d->created_at,
                'tahap' => $unit !== null ? "Ditugaskan kepada {$unit} untuk dibuatkan suratnya" : 'Didisposisikan',
                'pelaku' => $d->dari_jabatan_id !== null ? $d->dariJabatan->nama : $this->jabatanPelaku($d->dari, $d->created_at),
                'catatan' => null,
            ];
        }

        if ($surat->hasil_dikirim_pada !== null) {
            $tahap[] = ['waktu' => $surat->hasil_dikirim_pada, 'tahap' => 'Hasil dikirim kepada pengirim', 'pelaku' => $this->jabatanPelaku($surat->hasil_dikirim_oleh ? User::with('roles')->find($surat->hasil_dikirim_oleh) : null, $surat->hasil_dikirim_pada), 'catatan' => null];
        }

        return $this->urutkan($tahap);
    }

    /** Status akhir permohonan sebagai teks publik. */
    public function statusPermohonan(Permohonan $permohonan): string
    {
        return $permohonan->status->labelPublik();
    }

    public function statusSuratMasuk(SuratMasuk $surat): string
    {
        return match ($surat->status) {
            StatusSuratMasuk::Diterima => $surat->sudahDiteruskan() ? 'Menunggu disposisi dekan' : 'Diterima, menunggu diteruskan',
            StatusSuratMasuk::Didisposisikan => $surat->unit_pembuat_id !== null ? 'Surat sedang dibuat' : 'Sedang diproses',
            StatusSuratMasuk::Selesai, StatusSuratMasuk::Diarsipkan => 'Selesai',
        };
    }

    /** Jabatan yang diemban pelaku pada waktu itu; bila tidak ada, label peran, bila tidak ada pula "Petugas fakultas". */
    private function jabatanPelaku(?User $pelaku, CarbonInterface $waktu): string
    {
        if ($pelaku === null) {
            return 'Sistem';
        }

        $kunci = $pelaku->getKey().'|'.$waktu->toDateString();

        if (! array_key_exists($kunci, $this->tembolokJabatan)) {
            $this->tembolokJabatan[$kunci] = PemangkuJabatan::where('user_id', $pelaku->getKey())->berlakuPada($waktu)->with('jabatan')->get()
                ->sortBy(fn (PemangkuJabatan $p) => $p->jabatan->urutan)->first()?->jabatan->nama;
        }

        if ($this->tembolokJabatan[$kunci] !== null) {
            return $this->tembolokJabatan[$kunci];
        }

        if ($pelaku->unitPembuat()->exists()) {
            return 'Petugas unit pembuat surat';
        }

        foreach (self::LABEL_PERAN as $peran => $label) {
            if ($pelaku->hasRole($peran)) {
                return $label;
            }
        }

        return 'Petugas fakultas';
    }

    /**
     * @param  list<Tahap>  $tahap
     * @return list<Tahap>
     */
    private function urutkan(array $tahap): array
    {
        return Collection::make($tahap)->sortBy(fn (array $t) => $t['waktu']->getTimestamp())->values()->all();
    }
}
