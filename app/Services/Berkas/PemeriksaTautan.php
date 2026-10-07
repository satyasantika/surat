<?php

namespace App\Services\Berkas;

use App\Models\TautanBerkas;
use App\Services\Notifikasi\NotifikasiAlur;
use App\Support\HostAman;
use App\Support\UrlBerkas;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memeriksa keteraksesan tautan tanpa mengunduh isi. Anti-SSRF: host harus di daftar putih, semua IP hasil
 * DNS harus publik, koneksi dipaku ke IP tersebut, dan pengalihan tidak diikuti.
 */
class PemeriksaTautan
{
    public function periksa(TautanBerkas $tautan): string
    {
        $sebelum = $tautan->status_cek;
        $status = $this->cek($tautan->url);

        $tautan->forceFill(['status_cek' => $status, 'dicek_pada' => now()])->saveQuietly();

        if ($status === 'tidak_dapat_diakses' && $sebelum !== 'tidak_dapat_diakses') {
            app(NotifikasiAlur::class)->tautanMati($tautan);
        }

        return $status;
    }

    private function cek(string $url): string
    {
        $urai = UrlBerkas::urai($url);

        if ($urai === null || UrlBerkas::hostPemendek($urai['host']) || ! UrlBerkas::hostDiizinkan($urai['host'])) {
            return 'tidak_dapat_diakses';
        }

        $ip = HostAman::ipPublik($urai['host']);

        if ($ip === null) {
            return 'tidak_dapat_diakses';
        }

        try {
            $respons = Http::timeout((int) config('berkas.timeout_cek'))
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => defined('CURLOPT_RESOLVE') ? [CURLOPT_RESOLVE => ["{$urai['host']}:443:{$ip}"]] : [],
                ])
                ->withHeaders(['User-Agent' => 'PersuratanFKIP-PeriksaTautan'])
                ->head($url);

            return $respons->successful() ? 'dapat_diakses' : 'tidak_dapat_diakses';
        } catch (Throwable) {
            return 'tidak_dapat_diakses';
        }
    }
}
