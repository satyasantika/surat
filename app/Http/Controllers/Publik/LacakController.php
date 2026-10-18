<?php

namespace App\Http\Controllers\Publik;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusNaskah;
use App\Enums\StatusPermohonan;
use App\Http\Controllers\Controller;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Support\KodeLacak;
use App\Support\Pelacakan\Linimasa;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Pelacakan publik tanpa login (BR-27). Menampilkan HANYA: jenis layanan, perihal ringkas (disamarkan untuk surat
 * rahasia, BR-04), tahap beserta tanggal, jabatan pemroses, status akhir, dan tautan hasil bila sudah dikirim.
 * Tidak pernah menampilkan catatan internal, instruksi disposisi, nama pribadi, kontak, maupun isi surat.
 * Kode tidak sah dan kode yang tidak ada dijawab dengan pesan dan status yang sama.
 */
class LacakController extends Controller
{
    private const PESAN_TAK_DITEMUKAN = 'Kode lacak tidak ditemukan. Periksa kembali kode yang Anda terima.';

    public function form(Request $request): Response|RedirectResponse
    {
        $masukan = trim((string) $request->query('kode', ''));

        if ($masukan === '') {
            return $this->jawab('publik.lacak-form', ['galat' => null], 200);
        }

        $kode = KodeLacak::normalisasi($masukan);

        return $kode !== null
            ? redirect()->route('lacak.tampil', KodeLacak::tampil($kode))
            : $this->tidakDitemukan();
    }

    public function tampil(string $kode, Linimasa $linimasa, PenyimpananBerkas $berkas): Response
    {
        $normal = KodeLacak::normalisasi($kode);

        if ($normal === null) {
            return $this->tidakDitemukan();
        }

        if ($p = Permohonan::with(['jenis', 'naskahIzin'])->where('kode_lacak', $normal)->first()) {
            $naskah = $p->naskahIzin;
            $hasil = $p->tautanHasil();

            return $this->jawab('publik.lacak-tampil', [
                'kode' => KodeLacak::tampil($normal),
                'layanan' => $p->jenis->nama,
                'perihal' => Str::limit($p->perihal, 120),
                'tertutup' => false,
                'status' => $linimasa->statusPermohonan($p),
                'selesai' => $p->status === StatusPermohonan::Selesai,
                'tahap' => $this->bersihkan($linimasa->permohonan($p), false),
                'hasilUrl' => $p->status === StatusPermohonan::Selesai && $hasil !== null ? $berkas->urlAkses($hasil) : null,
                'verifikasiUrl' => $p->status === StatusPermohonan::Selesai && $naskah?->status === StatusNaskah::Terbit && $naskah->nomor !== null ? route('verifikasi', $naskah->getKey()) : null,
            ], 200);
        }

        if ($s = SuratMasuk::where('kode_lacak', $normal)->first()) {
            $tertutup = $s->klasifikasi_keamanan->tertutup();
            $hasil = $s->tautanHasil();

            return $this->jawab('publik.lacak-tampil', [
                'kode' => KodeLacak::tampil($normal),
                'layanan' => 'Surat kepada Dekan FKIP',
                'perihal' => $tertutup ? null : Str::limit($s->perihal, 120),
                'tertutup' => $tertutup,
                'status' => $linimasa->statusSuratMasuk($s),
                'selesai' => $s->hasil_dikirim_pada !== null,
                'tahap' => $this->bersihkan($linimasa->suratMasuk($s), $tertutup),
                // Hasil surat rahasia tidak ditampilkan di halaman publik; pengirim mengambilnya lewat fakultas.
                'hasilUrl' => ! $tertutup && $hasil !== null ? $berkas->urlAkses($hasil) : null,
                'verifikasiUrl' => null,
            ], 200);
        }

        return $this->tidakDitemukan();
    }

    /**
     * Hanya waktu, nama tahap, dan jabatan yang keluar dari sini. Surat rahasia: tahap digeneralkan dan jabatan dibuang.
     *
     * @param  list<array{waktu: CarbonInterface, tahap: string, pelaku: ?string, catatan: ?string}>  $tahap
     * @return list<array{waktu: CarbonInterface, tahap: string, pelaku: ?string}>
     */
    private function bersihkan(array $tahap, bool $tertutup): array
    {
        $akhir = count($tahap) - 1;

        return array_map(fn (array $t, int $i) => [
            'waktu' => $t['waktu'],
            'tahap' => $tertutup && $i > 0 && $i < $akhir ? 'Dalam proses' : ($tertutup && str_starts_with($t['tahap'], 'Ditugaskan kepada') ? 'Dalam proses' : $t['tahap']),
            'pelaku' => $tertutup ? null : $t['pelaku'],
        ], $tahap, array_keys($tahap));
    }

    private function tidakDitemukan(): Response
    {
        return $this->jawab('publik.lacak-form', ['galat' => self::PESAN_TAK_DITEMUKAN], 404);
    }

    /** @param  array<string, mixed>  $data */
    private function jawab(string $view, array $data, int $status): Response
    {
        return response(view($view, $data)->render(), $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
