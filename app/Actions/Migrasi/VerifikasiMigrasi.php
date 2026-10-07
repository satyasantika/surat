<?php

namespace App\Actions\Migrasi;

use App\Models\ImporLog;
use App\Models\Lpj;
use App\Models\Naskah;
use App\Models\NomorTerpakai;
use App\Models\Ormawa;
use App\Models\PemakaianRuanganLokal;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\User;
use App\Support\Pengaturan;
use App\Support\SesiRuangan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Pemeriksaan wajib sebelum potong (07-MIGRASI-DATA §8). Setiap cek: lulus/gagal beserta rincian. */
class VerifikasiMigrasi
{
    /** Pola data contoh per tabel.kolom yang tidak boleh tersisa (S-13). */
    private const KOLOM_CONTOH = [
        'users' => ['name', 'email'], 'ormawa' => ['nama', 'singkatan', 'akun_media', 'surel_organisasi', 'visi', 'misi'],
        'pengurus_ormawa' => ['nama', 'prodi'], 'permohonan' => ['nama_kegiatan', 'perihal', 'deskripsi'],
        'kabar' => ['judul', 'subjudul', 'isi'], 'galeri' => ['judul', 'url'], 'tautan_berkas' => ['url', 'label'], 'ruangan_lokal' => ['nama', 'gedung'],
    ];

    private const POLA_CONTOH = ['fakultas teknik', 'bem ft', 'ukm robotik', '@ormawahub.ac.id', 'unsplash'];

    /** @return list<array{cek: string, ok: bool, rincian: string}> */
    public function jalankan(): array
    {
        return [
            $this->jumlah(), $this->rekapStatus(), $this->ruangan(), $this->nomor(), $this->lpj(), $this->pengguna(), $this->dataContoh(), $this->privasi(),
        ];
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function jumlah(): array
    {
        $terakhir = [];

        foreach (ImporLog::orderBy('created_at')->orderBy('id')->get(['sheet', 'id_lama', 'status', 'tabel_baru', 'id_baru']) as $l) {
            $terakhir["{$l->sheet}|{$l->id_lama}"] = $l;
        }

        if ($terakhir === []) {
            return $this->hasil('Jumlah baris per sheet', false, 'Belum ada log impor.');
        }

        $galat = collect($terakhir)->where('status', ImporLog::GALAT)->count();
        $kelompok = collect($terakhir)->filter(fn ($l) => $l->id_baru !== null && $l->tabel_baru !== null)->groupBy(fn ($l) => $l->sheet.' → '.$l->tabel_baru);
        $baris = [];
        $ok = $galat === 0;

        foreach ($kelompok as $nama => $log) {
            $ada = DB::table(explode(' → ', $nama)[1])->whereIn('id', $log->pluck('id_baru')->unique()->all())->count();
            $dilog = $log->pluck('id_baru')->unique()->count();
            $ok = $ok && $ada === $dilog;
            $baris[] = "{$nama}: log {$dilog}, tabel {$ada}";
        }

        return $this->hasil('Jumlah baris per sheet', $ok, implode('; ', $baris).($galat > 0 ? "; baris galat: {$galat}" : ''));
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function rekapStatus(): array
    {
        $rekap = Permohonan::where('sumber', 'migrasi')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tanpaRiwayat = Permohonan::where('sumber', 'migrasi')->whereDoesntHave('riwayat')->count();

        return $this->hasil('Rekap status permohonan', $tanpaRiwayat === 0, ($rekap->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ') ?: 'tidak ada permohonan migrasi').($tanpaRiwayat > 0 ? "; tanpa riwayat: {$tanpaRiwayat}" : '').' (bandingkan dengan rekap OrmawaHub)');
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function ruangan(): array
    {
        $konfirmasi = PermohonanRuangan::where('status', 'dikonfirmasi')->get();
        $bentrok = 0;

        foreach ($konfirmasi->groupBy(fn ($r) => $r->kode_ruangan.'|'.$r->tanggal->toDateString()) as $hari) {
            foreach ($hari as $i => $a) {
                foreach ($hari->slice($i + 1) as $b) {
                    $a->permohonan_id !== $b->permohonan_id && SesiRuangan::bentrok($a->sesi, $b->sesi) ? $bentrok++ : null;
                }
            }
        }

        $hilang = 0;

        if (Pengaturan::get('layanan_ruangan') === 'lokal') {
            foreach ($konfirmasi->filter(fn ($r) => $r->tanggal->gte(now()->startOfDay())) as $r) {
                PemakaianRuanganLokal::where('permohonan_id', $r->permohonan_id)->whereDate('tanggal', $r->tanggal)->where('sesi', $r->sesi)->exists() ? null : $hilang++;
            }
        }

        return $this->hasil('Ruangan', $bentrok === 0 && $hilang === 0, "bentrok antar pemakaian dikonfirmasi: {$bentrok}; pemakaian mendatang belum di jadwal: {$hilang}");
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function nomor(): array
    {
        $arsip = Naskah::where('snapshot->migrasi', true)->get();
        $tanpa = $arsip->whereNull('nomor_terpakai_id')->count();
        $ganda = NomorTerpakai::select('nomor_lengkap')->groupBy('nomor_lengkap')->havingRaw('count(*) > 1')->count();
        $baris = [];

        foreach (NomorTerpakai::whereIn('id', $arsip->pluck('nomor_terpakai_id')->filter())->with('register')->get()->groupBy(fn ($n) => $n->register->kode.' '.$n->tahun) as $nama => $n) {
            $max = NomorTerpakai::where('register_nomor_id', $n->first()->register_nomor_id)->where('tahun', $n->first()->tahun)->max('urut');
            $baris[] = "{$nama}: nomor berikutnya {$max}+1 = ".($max + 1);
        }

        return $this->hasil('Register nomor', $ganda === 0, ($baris === [] ? 'tidak ada naskah arsip bernomor' : implode('; ', $baris))."; naskah arsip tanpa klaim nomor: {$tanpa}; nomor ganda: {$ganda}");
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function lpj(): array
    {
        $sampel = Lpj::whereNotNull('sumber_id_lama')->inRandomOrder()->limit(10)->with('nilai')->get();
        $salah = [];
        $baris = [];

        foreach ($sampel as $l) {
            $jumlah = round((float) $l->nilai->sum('nilai'), 2);
            $baris[] = "{$l->sumber_id_lama}: {$l->nilai->count()} nilai, jumlah {$jumlah}, akhir ".($l->nilai_akhir ?? '—');

            if ($l->status === Lpj::DINILAI && Pengaturan::get('rumus_nilai_lpj') !== 'persen' && abs($jumlah - (float) $l->nilai_akhir) > 0.005) {
                $salah[] = $l->sumber_id_lama;
            }
        }

        return $this->hasil('LPJ (sampel acak ≤10)', $salah === [], ($baris === [] ? 'tidak ada LPJ migrasi' : implode(' | ', $baris)).($salah !== [] ? '; TIDAK COCOK: '.implode(', ', $salah) : ''));
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function pengguna(): array
    {
        $migrasi = User::whereNotNull('sumber_id_lama')->with('roles')->get();
        $tanpaPeran = $migrasi->filter(fn (User $u) => $u->roles->isEmpty())->count();
        $belumVerifikasi = $migrasi->whereNull('email_verified_at')->count();
        $bersama = $migrasi->filter(fn (User $u) => $u->hasRole('pengurus-ormawa'))->count();
        $diundang = $migrasi->whereNotNull('diundang_pada')->count();

        return $this->hasil('Pengguna', $tanpaPeran === 0 && $belumVerifikasi === 0 && $bersama === 0, "akun migrasi: {$migrasi->count()}; tanpa peran: {$tanpaPeran}; surel belum terverifikasi: {$belumVerifikasi}; berperan pengurus-ormawa (akun bersama?): {$bersama}; sudah diundang: {$diundang}");
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function dataContoh(): array
    {
        $temuan = [];

        foreach (self::KOLOM_CONTOH as $tabel => $kolom) {
            foreach ($kolom as $k) {
                $n = DB::table($tabel)->where(function ($q) use ($k) {
                    foreach (self::POLA_CONTOH as $p) {
                        $q->orWhereRaw("LOWER(`{$k}`) LIKE ?", ['%'.$p.'%']);
                    }
                })->count();

                $n > 0 ? $temuan[] = "{$tabel}.{$k}: {$n}" : null;
            }
        }

        return $this->hasil('Data contoh tersisa', $temuan === [], $temuan === [] ? 'tidak ada' : implode(', ', $temuan));
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function privasi(): array
    {
        $kernel = app(Kernel::class);
        $ormawa = Ormawa::where('aktif', true)->with('pengurus.user')->get();
        $bocor = [];
        $halaman = 0;

        foreach ($ormawa as $o) {
            $respons = $kernel->handle(Request::create('/organisasi/'.$o->slug));
            $isi = (string) $respons->getContent();
            $halaman++;

            if ($respons->getStatusCode() !== 200) {
                $bocor[] = "{$o->slug}: status {$respons->getStatusCode()}";

                continue;
            }

            $rahasia = array_filter([$o->surel_organisasi, ...$o->pengurus->flatMap(fn ($p) => [$p->nim, $p->telepon, $p->user?->email])->all()]);

            foreach ($rahasia as $nilai) {
                str_contains($isi, (string) $nilai) ? $bocor[] = "{$o->slug}: memuat data pribadi/surel" : null;
            }
        }

        return $this->hasil('Privasi halaman publik ormawa', $bocor === [], "{$halaman} halaman diperiksa".($bocor === [] ? '' : '; masalah: '.implode(', ', array_unique($bocor))));
    }

    /** @return array{cek: string, ok: bool, rincian: string} */
    private function hasil(string $cek, bool $ok, string $rincian): array
    {
        return ['cek' => $cek, 'ok' => $ok, 'rincian' => $rincian];
    }
}
