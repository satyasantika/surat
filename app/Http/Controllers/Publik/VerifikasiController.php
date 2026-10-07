<?php

namespace App\Http\Controllers\Publik;

use App\Enums\StatusNaskah;
use App\Http\Controllers\Controller;
use App\Models\Naskah;
use App\Support\Mode;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Verifikasi publik naskah lewat QR (BR-10, RS-05). Hanya bidang putih: nomor, tanggal, jenis, perihal
 * (hanya klasifikasi biasa), penanda tangan & jabatan, mode, status, hash. Tidak ada isi, tautan, atau daftar.
 */
class VerifikasiController extends Controller
{
    private const STATUS_TERLIHAT = [StatusNaskah::Ditandatangani, StatusNaskah::Terbit, StatusNaskah::Dibatalkan];

    public function __invoke(string $id): Response
    {
        // 404 generik untuk id tak valid, tak ada, atau naskah yang belum/tidak berlaku sebagai dokumen terbit.
        $naskah = Str::isUuid($id)
            ? Naskah::with(['jenis', 'penandaTanganJabatan', 'penandaTanganUser'])->find($id)
            : null;

        abort_unless($naskah !== null && in_array($naskah->status, self::STATUS_TERLIHAT, true) && $naskah->nomor !== null, 404);

        $snapshot = $naskah->snapshot ?? [];
        $nonce = base64_encode(random_bytes(16));

        $isi = view('verifikasi.tampil', [
            'nonce' => $nonce,
            'nomor' => $naskah->nomor,
            'tanggal' => $naskah->tanggal_naskah?->locale('id')->translatedFormat('d F Y'),
            'jenis' => $naskah->jenis->nama,
            'perihal' => $naskah->klasifikasi_keamanan->value === 'biasa' ? $naskah->perihal : null,
            'penandaTangan' => $snapshot['penanda_tangan']['nama'] ?? $naskah->penandaTanganUser?->name,
            'jabatan' => $snapshot['penanda_tangan']['jabatan'] ?? $naskah->penandaTanganJabatan->nama,
            'jabatanDasar' => $snapshot['penanda_tangan']['jabatan_dasar'] ?? null,
            'mode' => Mode::label($naskah->mode_tanda_tangan),
            'dibatalkan' => $naskah->status === StatusNaskah::Dibatalkan,
            'tanggalBatal' => $naskah->dibatalkan_pada?->locale('id')->translatedFormat('d F Y'),
            'alasanBatal' => $naskah->alasan_batal ? Str::limit($naskah->alasan_batal, 120) : null,
            'hash' => $naskah->hash_pdf,
            'migrasi' => $naskah->hash_pdf === null && $naskah->snapshot === null,
        ])->render();

        return response($isi, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'nonce-{$nonce}'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ]);
    }
}
