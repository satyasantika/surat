<?php

namespace App\Services\Naskah;

use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\HostAman;
use App\Support\UrlBerkas;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mengambil gambar tanda tangan visual dari tautan ttd_visual pejabat, hanya di sisi server, lalu
 * mengembalikannya sebagai data URI (URL tidak pernah sampai ke klien). Anti-SSRF: host daftar putih
 * (atau lh3.googleusercontent.com untuk Drive), IP publik dipaku, tanpa redirect, ukuran dibatasi.
 */
class PengambilGambarTtd
{
    private const BATAS_BYTE = 1_048_576;

    private const TIPE = ['image/png', 'image/jpeg'];

    public function dataUri(User $pejabat): string
    {
        $tautan = TautanBerkas::where('pemilik_type', $pejabat->getMorphClass())
            ->where('pemilik_id', $pejabat->getKey())
            ->where('jenis', 'ttd_visual')
            ->latest()->first();

        if ($tautan === null) {
            throw $this->gagal('Penanda tangan belum memiliki gambar tanda tangan visual (tautan ttd_visual).');
        }

        $url = $tautan->drive_file_id
            ? "https://lh3.googleusercontent.com/d/{$tautan->drive_file_id}"
            : $tautan->url;

        $urai = UrlBerkas::urai($url);

        if ($urai === null || ! (UrlBerkas::hostDiizinkan($urai['host']) || $urai['host'] === 'lh3.googleusercontent.com')) {
            throw $this->gagal('Alamat gambar tanda tangan tidak diizinkan.');
        }

        $ip = HostAman::ipPublik($urai['host']);

        if ($ip === null) {
            throw $this->gagal('Alamat gambar tanda tangan tidak dapat dijangkau dengan aman.');
        }

        try {
            $respons = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => false,
                    'curl' => defined('CURLOPT_RESOLVE') ? [CURLOPT_RESOLVE => ["{$urai['host']}:443:{$ip}"]] : [],
                ])
                ->get($url);
        } catch (Throwable) {
            throw $this->gagal('Gambar tanda tangan tidak dapat diambil.');
        }

        $tipe = strtolower(trim(explode(';', (string) $respons->header('Content-Type'))[0]));
        $isi = $respons->body();

        if (! $respons->successful() || ! in_array($tipe, self::TIPE, true) || strlen($isi) === 0 || strlen($isi) > self::BATAS_BYTE) {
            throw $this->gagal('Gambar tanda tangan harus berkas PNG/JPEG maksimal 1 MB yang dapat diakses.');
        }

        // Isi harus benar-benar gambar, bukan sekadar header yang menyamar.
        if (@getimagesizefromstring($isi) === false) {
            throw $this->gagal('Berkas tanda tangan bukan gambar yang valid.');
        }

        return "data:{$tipe};base64,".base64_encode($isi);
    }

    private function gagal(string $pesan): ValidationException
    {
        return ValidationException::withMessages(['mode_tanda_tangan' => $pesan]);
    }
}
