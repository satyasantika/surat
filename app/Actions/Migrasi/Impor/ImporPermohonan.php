<?php

namespace App\Actions\Migrasi\Impor;

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusNaskah;
use App\Enums\StatusPermohonan;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\ImporLog;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\JenisPermohonan;
use App\Models\Naskah;
use App\Models\NomorTerpakai;
use App\Models\PemakaianRuanganLokal;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\PersetujuanWd;
use App\Models\RegisterNomor;
use App\Models\RiwayatPermohonan;
use App\Models\RuanganLokal;
use App\Support\Migrasi\Bantu;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\PenguraiTanggal;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Requests → permohonan, ruangan, riwayat, disposisi, persetujuan WD, tautan, dan naskah arsip (§5).
 * Pelaku di `steps` hanya disimpan sebagai pelaku_lama (tidak terautentikasi); diajukan_oleh = pelaksana impor.
 */
class ImporPermohonan
{
    private const BOOKING_MANUAL = 'Booking manual oleh admin.';

    private const KODE_JENIS = [
        'permohonan kegiatan dan ruangan' => 'kegiatan-ruangan',
        'permohonan kegiatan dan ruangan dan fasilitas rektorat' => 'kegiatan-ruangan-rektorat',
        'pengantar proposal' => 'pengantar-proposal',
        'permohonan kegiatan' => 'kegiatan',
    ];

    /** @var array<int, StatusPermohonan> currentStep lama → status (fallback bila nama tahap tak terbaca) */
    private const TAHAP_ANGKA = [
        1 => StatusPermohonan::Diajukan, 2 => StatusPermohonan::ValidasiAdmin, 3 => StatusPermohonan::DisposisiDekan,
        4 => StatusPermohonan::PersetujuanWd, 5 => StatusPermohonan::PersetujuanWd, 6 => StatusPermohonan::RekomendasiKasubag, 7 => StatusPermohonan::Penerbitan,
    ];

    /** @param  list<array<string, mixed>>  $baris */
    public function jalankan(KonteksImpor $k, array $baris): void
    {
        $urut = [];

        foreach ($baris as $i => $r) {
            $tgl = PenguraiTanggal::urai($r['tanggalPengajuan'] ?? null);
            $urut[] = ['i' => $i, 'tgl' => $tgl === null ? PHP_INT_MAX : $tgl->timestamp, 'r' => $r];
        }

        usort($urut, fn ($a, $b) => [$a['tgl'], $a['i']] <=> [$b['tgl'], $b['i']]);
        $dipakai = [];

        foreach ($urut as ['r' => $r]) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            if (PolaContoh::barisContoh($r)) {
                $k->catat('Requests', $id, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            $dipakai[$id] = ($dipakai[$id] ?? 0) + 1;
            $kunci = $dipakai[$id] > 1 ? "{$id}-dup{$dipakai[$id]}" : $id;

            if ($baru = $k->sebelumnya('Requests', $kunci)) {
                $k->peta['permohonan'][$kunci] = $baru;
                $k->catat('Requests', $kunci, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', 'permohonan', $baru);

                continue;
            }

            try {
                [$tabel, $idBaru, $peringatan] = DB::transaction(fn () => Sel::teks($r['deskripsi'] ?? null) === self::BOOKING_MANUAL
                    ? $this->bookingManual($k, $r)
                    : $this->permohonan($k, $r, $kunci, $dipakai[$id] > 1));

                if ($tabel === 'permohonan') {
                    $k->peta['permohonan'][$kunci] = $idBaru;
                }

                $k->catat('Requests', $kunci, $peringatan === [] ? ImporLog::OK : ImporLog::PERINGATAN, $peringatan === [] ? null : implode(' ', $peringatan), $tabel, $idBaru);
            } catch (Throwable $e) {
                $k->catat('Requests', $kunci, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{0: string, 1: ?string, 2: list<string>}
     */
    private function bookingManual(KonteksImpor $k, array $r): array
    {
        $mulai = PenguraiTanggal::urai($r['tanggal'] ?? null) ?? throw new RuntimeException('Tanggal booking tidak terbaca.');
        $selesai = PenguraiTanggal::urai($r['tanggalSelesai'] ?? null) ?? $mulai;
        $kode = $this->kodeRuangan($k, Sel::teks($r['roomId'] ?? null));

        if ($k->modeRuangan !== 'lokal') {
            return ['pemakaian', null, ["Mode {$k->modeRuangan}: booking manual ruangan '{$kode}' perlu dicatat di sistem Aset (tidak diimpor)."]];
        }

        $ruangan = $kode !== null ? RuanganLokal::firstWhere('kode', $kode) : null;

        if ($ruangan === null) {
            throw new RuntimeException('Ruangan booking manual tidak terpetakan.');
        }

        $peringatan = [];
        $pertama = null;

        foreach ($this->sesi($r['sesiBooking'] ?? null, $peringatan) as $sesi) {
            foreach ($this->tanggalRentang($mulai, $selesai) as $tgl) {
                $p = PemakaianRuanganLokal::create(['ruangan_lokal_id' => $ruangan->getKey(), 'tanggal' => $tgl->toDateString(), 'sesi' => $sesi, 'keterangan' => Sel::teks($r['namaKegiatan'] ?? null) ?? 'Booking manual (impor)', 'permohonan_id' => null]);
                $pertama ??= $p->getKey();
            }
        }

        return ['pemakaian_ruangan_lokal', $pertama, $peringatan];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function permohonan(KonteksImpor $k, array $r, string $nomorLama, bool $ganda): array
    {
        $peringatan = $ganda ? ["Id ganda (S-06): diimpor dengan nomor_lama {$nomorLama}."] : [];
        $pelaksana = $k->pelaksana ?? throw new RuntimeException('Pelaksana impor tidak ditentukan.');

        $ormawaId = Bantu::ormawaDariNama($k, Sel::teks($r['ormawa'] ?? null))
            ?? throw new RuntimeException("Ormawa '".Sel::teks($r['ormawa'] ?? null)."' tidak cocok; perbaiki ormawa.csv.");
        $jenis = $this->jenis(Sel::teks($r['jenisPermohonan'] ?? null)) ?? throw new RuntimeException("Jenis permohonan '".Sel::teks($r['jenisPermohonan'] ?? null)."' tidak dikenal.");
        $diajukan = PenguraiTanggal::urai($r['tanggalPengajuan'] ?? null) ?? throw new RuntimeException('tanggalPengajuan tidak terbaca.');
        $mulai = PenguraiTanggal::urai($r['tanggal'] ?? null) ?? throw new RuntimeException('Tanggal kegiatan tidak terbaca.');
        $selesai = PenguraiTanggal::urai($r['tanggalSelesai'] ?? null) ?? $mulai;

        $steps = $this->steps($r['steps'] ?? null);
        [$status, $selesaiPada] = $this->status($r, $steps, $diajukan, $peringatan);

        $p = new Permohonan([
            'ormawa_id' => $ormawaId, 'jenis_permohonan_id' => $jenis->getKey(), 'diajukan_oleh' => $pelaksana->getKey(),
            'nama_kegiatan' => Str::limit(Sel::teks($r['namaKegiatan'] ?? null) ?? throw new RuntimeException('namaKegiatan kosong.'), 255, ''),
            'perihal' => Str::limit(Sel::teks($r['perihal'] ?? null) ?? 'Permohonan kegiatan', 255, ''),
            'nomor_surat_ormawa' => Str::limit((string) Sel::teks($r['nomorSurat'] ?? null), 100, '') ?: null,
            'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => max($selesai, $mulai)->toDateString(),
            'jam_mulai' => $this->jam($r['jamMulai'] ?? null), 'jam_selesai' => $this->jam($r['jamSelesai'] ?? null),
            'deskripsi' => Sel::teks($r['deskripsi'] ?? null) ?? '-',
            'penanggung_jawab' => $this->penanggungJawab($r),
            'fasilitas_rektorat' => $this->fasilitas($r),
        ]);
        $p->forceFill([
            'nomor' => 'IMP-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 24), 'nomor_lama' => $nomorLama, 'status' => $status, 'diajukan_pada' => $diajukan,
            'selesai_pada' => $selesaiPada, 'sumber' => 'migrasi',
        ])->save();

        $register = RegisterNomor::where('kode', 'permohonan')->firstOrFail();
        $p->forceFill(['nomor' => app(AmbilNomorBerikutnya::class)->jalankan($register, $p, [], $diajukan)->nomor_lengkap])->save();

        $this->riwayat($p, $steps, $status, $diajukan);
        $this->ruangan($k, $p, $r, $status, $mulai, $selesai, $peringatan);
        $this->disposisi($k, $p, $r, $steps, $status, $diajukan, $peringatan);

        foreach (['suratUrl' => ['surat_permohonan', 'Surat permohonan'], 'proposalUrl' => ['proposal', 'Proposal']] as $kolom => [$jenisTautan, $label]) {
            $url = Sel::teks($r[$kolom] ?? null);

            if ($url === null) {
                continue;
            }

            Bantu::urlBerkas($url) !== null
                ? app(PenyimpananBerkas::class)->simpan($p, $jenisTautan, $url, $label, null)
                : $peringatan[] = "Tautan {$label} dibuang (tidak memenuhi kebijakan berkas).";
        }

        if (Sel::teks($r['suratName'] ?? null) !== null && Sel::teks($r['suratUrl'] ?? null) === null) {
            $peringatan[] = 'Nama berkas surat tanpa URL; berkas perlu ditautkan ulang.';
        }

        $this->naskahArsip($k, $p, $r, $jenis, $diajukan, $selesaiPada, $peringatan);

        if (Sel::teks($r['draftSurat'] ?? null) !== null || Sel::teks($r['draftSuratContent'] ?? null) !== null) {
            $peringatan[] = 'Draf surat tidak dimigrasikan (S-10).';
        }

        return ['permohonan', $p->getKey(), $peringatan];
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  array<int|string, array<string, mixed>>  $steps
     * @param  list<string>  $peringatan
     * @return array{0: StatusPermohonan, 1: ?CarbonInterface}
     */
    private function status(array $r, array $steps, CarbonImmutable $diajukan, array &$peringatan): array
    {
        $lama = strtolower(Sel::teks($r['status'] ?? null) ?? '');
        $terakhir = $diajukan;

        foreach ($steps as $s) {
            $t = PenguraiTanggal::urai($s['date'] ?? null);
            $terakhir = $t !== null && $t->gt($terakhir) ? $t : $terakhir;
        }

        if ($lama === 'approved') {
            return [StatusPermohonan::Selesai, $terakhir];
        }

        if ($lama === 'rejected') {
            return [StatusPermohonan::Ditolak, $terakhir];
        }

        if (in_array($lama, ['cancelled', 'canceled', 'dibatalkan'], true)) {
            return [StatusPermohonan::Dibatalkan, $terakhir];
        }

        foreach ($steps as $s) {
            if (strtolower((string) ($s['status'] ?? '')) !== 'completed' && ($tahap = $this->tahap((string) ($s['name'] ?? ''))) !== null) {
                return [$tahap, null];
            }
        }

        $angka = Sel::int($r['currentStep'] ?? null);

        if ($angka !== null && isset(self::TAHAP_ANGKA[$angka])) {
            return [self::TAHAP_ANGKA[$angka], null];
        }

        $peringatan[] = "Tahap berjalan tidak terbaca (status '{$lama}'); diset ke diajukan.";

        return [StatusPermohonan::Diajukan, null];
    }

    private function tahap(string $nama): ?StatusPermohonan
    {
        $n = mb_strtolower($nama);

        return match (true) {
            str_contains($n, 'validasi') => StatusPermohonan::ValidasiAdmin,
            str_contains($n, 'disposisi') => StatusPermohonan::DisposisiDekan,
            str_contains($n, 'wd') || str_contains($n, 'wakil') => StatusPermohonan::PersetujuanWd,
            str_contains($n, 'kasubag') || str_contains($n, 'rekomendasi') => StatusPermohonan::RekomendasiKasubag,
            str_contains($n, 'terbit') || str_contains($n, 'surat') => StatusPermohonan::Penerbitan,
            str_contains($n, 'ajuk') || str_contains($n, 'submit') || str_contains($n, 'pengaju') => StatusPermohonan::Diajukan,
            default => null,
        };
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $steps
     */
    private function riwayat(Permohonan $p, array $steps, StatusPermohonan $akhir, CarbonImmutable $diajukan): void
    {
        $dari = null;
        $mikro = 0;
        $selesai = [];

        foreach ($steps as $s) {
            if (strtolower((string) ($s['status'] ?? '')) !== 'completed') {
                continue;
            }

            $selesai[] = ['tahap' => $this->tahap((string) ($s['name'] ?? '')), 'tgl' => PenguraiTanggal::urai($s['date'] ?? null) ?? $diajukan, 'actor' => Sel::teks($s['actor'] ?? null), 'notes' => Sel::teks($s['notes'] ?? null)];
        }

        usort($selesai, fn ($a, $b) => $a['tgl'] <=> $b['tgl']);

        foreach ($selesai as $s) {
            $ke = $s['tahap'] ?? $akhir;
            $this->tulisRiwayat($p, $dari, $ke, $s['tgl'], $mikro++, $s['actor'], $s['notes']);
            $dari = $ke;
        }

        if ($dari !== $akhir) {
            $this->tulisRiwayat($p, $dari, $akhir, $selesai === [] ? $diajukan : end($selesai)['tgl'], $mikro, null, 'Status akhir hasil impor OrmawaHub.');
        }
    }

    private function tulisRiwayat(Permohonan $p, ?StatusPermohonan $dari, StatusPermohonan $ke, CarbonInterface $tgl, int $mikro, ?string $pelaku, ?string $catatan): void
    {
        $r = new RiwayatPermohonan(['permohonan_id' => $p->getKey(), 'dari_status' => $dari?->value, 'ke_status' => $ke->value, 'pelaku_lama' => $pelaku !== null ? Str::limit($pelaku, 150, '') : null, 'catatan' => $catatan]);
        $r->forceFill(['oleh' => null, 'created_at' => Carbon::parse($tgl->toDateString().' 00:00:00')->addMicroseconds($mikro)])->save();
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  list<string>  $peringatan
     */
    private function ruangan(KonteksImpor $k, Permohonan $p, array $r, StatusPermohonan $status, CarbonImmutable $mulai, CarbonImmutable $selesai, array &$peringatan): void
    {
        $roomId = Sel::teks($r['roomId'] ?? null);

        if ($roomId === null) {
            return;
        }

        $kode = $this->kodeRuangan($k, $roomId);

        if ($kode === null) {
            $peringatan[] = "Ruangan '{$roomId}' tidak terpetakan; ruangan permohonan tidak diimpor.";

            return;
        }

        $nama = $k->peta['ruangan_nama'][$roomId] ?? $kode;
        $statusRuangan = match ($status) {
            StatusPermohonan::Selesai => 'dikonfirmasi', StatusPermohonan::Ditolak, StatusPermohonan::Dibatalkan => 'dilepas', default => 'ditahan',
        };
        $lokal = $k->modeRuangan === 'lokal' ? RuanganLokal::firstWhere('kode', $kode) : null;

        foreach ($this->sesi($r['sesiBooking'] ?? null, $peringatan) as $sesi) {
            foreach ($this->tanggalRentang($mulai, $selesai) as $tgl) {
                PermohonanRuangan::create(['permohonan_id' => $p->getKey(), 'kode_ruangan' => $kode, 'nama_ruangan' => Str::limit($nama, 150, ''), 'tanggal' => $tgl->toDateString(), 'sesi' => $sesi, 'status' => $statusRuangan]);

                if ($lokal !== null && $statusRuangan === 'dikonfirmasi') {
                    try {
                        DB::transaction(fn () => PemakaianRuanganLokal::create(['ruangan_lokal_id' => $lokal->getKey(), 'tanggal' => $tgl->toDateString(), 'sesi' => $sesi, 'keterangan' => $p->nama_kegiatan, 'permohonan_id' => $p->getKey()]));
                    } catch (QueryException) {
                        $peringatan[] = "Pemakaian {$kode} {$tgl->toDateString()} {$sesi} tidak dicatat di jadwal lokal.";
                    }
                }
            }
        }

        if ($k->modeRuangan !== 'lokal' && $statusRuangan === 'dikonfirmasi') {
            $peringatan[] = 'Mode aset_api: pemakaian ruangan dikonfirmasi perlu dipastikan tercatat di sistem Aset.';
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  array<int|string, array<string, mixed>>  $steps
     * @param  list<string>  $peringatan
     */
    private function disposisi(KonteksImpor $k, Permohonan $p, array $r, array $steps, StatusPermohonan $status, CarbonImmutable $diajukan, array &$peringatan): void
    {
        $tujuan = $this->daftar(Sel::json($r['disposedTo'] ?? null) ?? Sel::teks($r['disposedTo'] ?? null));

        if ($tujuan === []) {
            return;
        }

        $setuju = array_map('strtolower', $this->daftar(Sel::json($r['approvedWD'] ?? null) ?? Sel::teks($r['approvedWD'] ?? null)));
        $tglDisposisi = $diajukan;

        foreach ($steps as $s) {
            if ($this->tahap((string) ($s['name'] ?? '')) === StatusPermohonan::DisposisiDekan && ($t = PenguraiTanggal::urai($s['date'] ?? null)) !== null) {
                $tglDisposisi = $t;
            }
        }

        $dekan = Bantu::pemangku('dekan', $tglDisposisi);
        $dari = $dekan ?? $k->pelaksana ?? throw new RuntimeException('Pelaksana impor tidak ditentukan.');
        $dekan === null ? $peringatan[] = 'Pemangku dekan tidak ditemukan; disposisi dicatat atas nama pelaksana impor.' : null;

        $d = new Disposisi(['permohonan_id' => $p->getKey(), 'nomor' => Str::limit((string) Sel::teks($r['nomorDisposisi'] ?? null), 80, '') ?: null, 'dari_user_id' => $dari->getKey(), 'dari_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')?->getKey(), 'instruksi' => ['tindak_lanjuti'], 'catatan' => Sel::teks($r['dekanNotes'] ?? null), 'batas_waktu' => $tglDisposisi->addDays(7)->setTime(17, 0), 'sifat' => 'biasa']);
        $d->forceFill(['created_at' => $tglDisposisi, 'updated_at' => $tglDisposisi])->save();

        foreach ($tujuan as $kodeLama) {
            $kodeJabatan = $k->pemetaan->jabatanWd($kodeLama);
            $user = $kodeJabatan !== null ? Bantu::pemangku($kodeJabatan, $tglDisposisi) : null;
            $jabatan = $kodeJabatan !== null ? Jabatan::firstWhere('kode', $kodeJabatan) : null;

            if ($jabatan === null) {
                $peringatan[] = "WD '{$kodeLama}' tidak ada di wd.csv; penerima disposisi dan persetujuan dilewati.";

                continue;
            }

            $putusan = in_array(strtolower($kodeLama), $setuju, true) ? 'setuju' : ($status === StatusPermohonan::Ditolak ? 'tolak' : 'menunggu');

            if ($user !== null) {
                $pn = new DisposisiPenerima(['disposisi_id' => $d->getKey(), 'user_id' => $user->getKey(), 'jabatan_id' => $jabatan->getKey(), 'status' => $putusan === 'menunggu' ? 'diterima' : 'selesai']);
                $putusan !== 'menunggu' ? $pn->forceFill(['selesai_pada' => $this->tanggalTahap($steps, $tglDisposisi)]) : null;
                $pn->save();
            } else {
                $peringatan[] = "Pemangku {$kodeJabatan} tidak ditemukan; penerima disposisi dilewati.";
            }

            $ps = new PersetujuanWd(['permohonan_id' => $p->getKey(), 'jabatan_id' => $jabatan->getKey(), 'user_id' => $putusan === 'menunggu' ? null : $user?->getKey(), 'putusan' => $putusan, 'catatan' => $putusan === 'menunggu' ? null : Sel::teks($r['wdNotes'] ?? null)]);
            $putusan !== 'menunggu' ? $ps->forceFill(['diputus_pada' => $this->tanggalTahap($steps, $tglDisposisi)]) : null;
            $ps->save();
        }
    }

    /** @param  array<int|string, array<string, mixed>>  $steps */
    private function tanggalTahap(array $steps, CarbonImmutable $bawaan): CarbonImmutable
    {
        $hasil = $bawaan;

        foreach ($steps as $s) {
            if ($this->tahap((string) ($s['name'] ?? '')) === StatusPermohonan::PersetujuanWd && ($t = PenguraiTanggal::urai($s['date'] ?? null)) !== null && $t->gt($hasil)) {
                $hasil = $t;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  list<string>  $peringatan
     */
    private function naskahArsip(KonteksImpor $k, Permohonan $p, array $r, JenisPermohonan $jenis, CarbonImmutable $diajukan, ?CarbonInterface $selesaiPada, array &$peringatan): void
    {
        $jenisNaskah = $jenis->jenis_naskah_id ? JenisNaskah::with('register')->find($jenis->jenis_naskah_id) : null;
        $jabatanDekan = Jabatan::firstWhere('kode', 'dekan');
        $tanggal = ($selesaiPada ?? $diajukan)->toDateString();
        $pertama = null;

        foreach (['nomorSuratTerbit', 'nomorSuratTerbit2'] as $kolom) {
            $nomor = Sel::teks($r[$kolom] ?? null);

            if ($nomor === null) {
                continue;
            }

            if ($jenisNaskah === null || $jabatanDekan === null) {
                $peringatan[] = "Nomor surat '{$nomor}' tidak diarsipkan: jenis naskah/jabatan dekan belum tersedia.";

                continue;
            }

            if (Naskah::withTrashed()->where('nomor', $nomor)->exists()) {
                $peringatan[] = "Nomor surat '{$nomor}' sudah ada di arsip (ganda); naskah arsip tidak dibuat ulang.";

                continue;
            }

            $n = new Naskah(['jenis_naskah_id' => $jenisNaskah->getKey(), 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => Str::limit($p->perihal, 500, ''), 'data' => [], 'status' => StatusNaskah::Terbit->value, 'penyusun_id' => $k->pelaksana?->getKey(), 'penanda_tangan_jabatan_id' => $jabatanDekan->getKey(), 'mode_tanda_tangan' => 'basah', 'permohonan_id' => $p->getKey()]);
            $n->forceFill(['nomor' => Str::limit($nomor, 150, ''), 'tanggal_naskah' => $tanggal, 'snapshot' => ['migrasi' => true], 'hash_pdf' => null, 'ditandatangani_pada' => $tanggal.' 00:00:00', 'diterbitkan_pada' => $tanggal.' 00:00:00'])->save();

            if (preg_match('#^\s*(\d{1,6})\s*/.+/(\d{4})\s*$#', $nomor, $m) === 1 && $jenisNaskah->register !== null) {
                $tahun = $jenisNaskah->register->reset === 'tahunan' ? (int) $m[2] : 0;

                try {
                    $nt = DB::transaction(fn () => NomorTerpakai::create(['register_nomor_id' => $jenisNaskah->register_nomor_id, 'tahun' => $tahun, 'urut' => (int) $m[1], 'nomor_lengkap' => Str::limit($nomor, 150, ''), 'pemilik_type' => $n->getMorphClass(), 'pemilik_id' => $n->getKey(), 'dibatalkan' => false]));
                    $n->forceFill(['nomor_terpakai_id' => $nt->getKey()])->save();
                } catch (QueryException) {
                    $peringatan[] = "Urut {$m[1]} tahun {$m[2]} sudah terpakai di register; nomor '{$nomor}' tidak diklaim ulang.";
                }
            } else {
                $peringatan[] = "Nomor surat '{$nomor}' tidak sesuai pola; register tidak diperbarui.";
            }

            $pertama ??= $n;
        }

        if ($pertama !== null) {
            $p->forceFill(['naskah_izin_id' => $pertama->getKey()])->save();
            $url = Sel::teks($r['officialLetterUrl'] ?? null);

            if ($url !== null) {
                Bantu::urlBerkas($url) !== null
                    ? app(PenyimpananBerkas::class)->simpan($pertama, 'naskah_basah', $url, 'Surat resmi (pindaian)', null)
                    : $peringatan[] = 'Tautan surat resmi dibuang (tidak memenuhi kebijakan berkas).';
            }
        }
    }

    private function jenis(?string $teks): ?JenisPermohonan
    {
        if ($teks === null) {
            return null;
        }

        $n = mb_strtolower(preg_replace('/\s+/', ' ', str_replace(['&', '+'], 'dan', $teks)) ?? $teks);
        $kode = self::KODE_JENIS[$n] ?? (str_contains($n, 'rektorat') ? 'kegiatan-ruangan-rektorat' : (str_contains($n, 'ruangan') ? 'kegiatan-ruangan' : (str_contains($n, 'pengantar') ? 'pengantar-proposal' : (str_contains($n, 'kegiatan') ? 'kegiatan' : null))));

        return $kode !== null ? JenisPermohonan::firstWhere('kode', $kode) : null;
    }

    /** @return array<int|string, array<string, mixed>> */
    private function steps(mixed $nilai): array
    {
        $s = Sel::json($nilai);

        if (! is_array($s)) {
            return [];
        }

        $s = array_filter($s, 'is_array');
        uksort($s, fn ($a, $b) => (is_numeric($a) && is_numeric($b)) ? (int) $a <=> (int) $b : strcmp((string) $a, (string) $b));

        return $s;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function penanggungJawab(array $r): array
    {
        $hasil = [];

        foreach (['ketua', 'wakil', 'sekretaris'] as $peran) {
            $j = Sel::json($r[$peran] ?? null);
            $teks = Sel::teks($r[$peran] ?? null);

            if (is_array($j)) {
                $hasil[$peran] = $j;
            } elseif ($teks !== null) {
                $hasil[$peran] = ['nama' => $teks];
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{nama: string, jumlah: int, keterangan: ?string}>|null
     */
    private function fasilitas(array $r): ?array
    {
        $f = Sel::json($r['fasilitas'] ?? null);
        $teks = Sel::teks($r['fasilitas'] ?? null);
        $daftar = [];

        foreach (is_array($f) ? $f : ($teks !== null ? preg_split('/[,;\n]+/', $teks) ?: [] : []) as $item) {
            $nama = is_array($item) ? ($item['nama'] ?? $item['name'] ?? null) : $item;
            $nama = is_string($nama) ? trim($nama) : null;

            if ($nama !== null && $nama !== '') {
                $daftar[] = ['nama' => Str::limit($nama, 150, ''), 'jumlah' => is_array($item) ? max(1, (int) ($item['jumlah'] ?? $item['qty'] ?? 1)) : 1, 'keterangan' => null];
            }
        }

        return $daftar === [] ? null : $daftar;
    }

    /** @return list<string> */
    private function daftar(mixed $nilai): array
    {
        $d = is_array($nilai) ? $nilai : (is_string($nilai) ? preg_split('/[,;|]+/', $nilai) : []);

        return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $d ?: []), fn ($x) => $x !== ''));
    }

    /**
     * @param  list<string>  $peringatan
     * @return list<string>
     */
    private function sesi(mixed $nilai, array &$peringatan): array
    {
        $j = Sel::json($nilai);
        $tokens = is_array($j) ? $j : (Sel::teks($nilai) !== null ? preg_split('/[,;|]+/', (string) Sel::teks($nilai)) : []);
        $hasil = [];

        foreach ($tokens ?: [] as $t) {
            $n = mb_strtolower(trim((string) $t));

            if ($n === '') {
                continue;
            }

            $sesi = match (true) {
                str_contains($n, 'seharian'), str_contains($n, 'full'), str_contains($n, 'sehari') => 'seharian',
                str_contains($n, 'pagi') => 'pagi',
                str_contains($n, 'siang') => 'siang',
                default => null,
            };

            if ($sesi === null) {
                $peringatan[] = "Sesi '{$t}' tidak dikenal; dipakai seharian.";
                $sesi = 'seharian';
            }

            $hasil[$sesi] = $sesi;
        }

        return array_values($hasil === [] ? ['seharian' => 'seharian'] : $hasil);
    }

    /** @return list<CarbonImmutable> */
    private function tanggalRentang(CarbonImmutable $mulai, CarbonImmutable $selesai): array
    {
        $hasil = [];

        for ($t = $mulai; $t->lte($selesai) && count($hasil) < 31; $t = $t->addDay()) {
            $hasil[] = $t;
        }

        return $hasil;
    }

    private function jam(mixed $nilai): ?string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('H:i:s');
        }

        $t = Sel::teks($nilai);

        if ($t !== null && preg_match('/^(\d{1,2})[:.](\d{2})/', $t, $m) === 1 && (int) $m[1] < 24 && (int) $m[2] < 60) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        if (is_numeric($nilai) && (float) $nilai >= 0 && (float) $nilai < 1) {
            $detik = (int) round((float) $nilai * 86400);

            return sprintf('%02d:%02d:00', intdiv($detik, 3600), intdiv($detik % 3600, 60));
        }

        return null;
    }

    private function kodeRuangan(KonteksImpor $k, ?string $roomId): ?string
    {
        return $roomId === null ? null : ($k->peta['ruangan'][$roomId] ?? $k->pemetaan->ruangan($roomId));
    }
}
