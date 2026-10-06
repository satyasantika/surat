<?php

namespace App\Services\Berkas;

use App\Models\TautanBerkas;
use App\Support\UrlBerkas;
use Closure;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memeriksa keteraksesan tautan tanpa mengunduh isi. Anti-SSRF: host harus di daftar putih, semua IP hasil
 * DNS harus publik, koneksi dipaku ke IP tersebut, dan pengalihan tidak diikuti.
 */
class PemeriksaTautan
{
    /** @var (Closure(string): list<string>)|null  penyelesai DNS, dapat diganti di uji */
    public static ?Closure $penyelesai = null;

    public function periksa(TautanBerkas $tautan): string
    {
        $status = $this->cek($tautan->url);

        $tautan->forceFill(['status_cek' => $status, 'dicek_pada' => now()])->saveQuietly();

        return $status;
    }

    private function cek(string $url): string
    {
        $urai = UrlBerkas::urai($url);

        if ($urai === null || UrlBerkas::hostPemendek($urai['host']) || ! UrlBerkas::hostDiizinkan($urai['host'])) {
            return 'tidak_dapat_diakses';
        }

        $ip = $this->ipPublik($urai['host']);

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

    /** IP publik pertama dari host; null bila ada IP pribadi/terlindung atau DNS gagal. */
    private function ipPublik(string $host): ?string
    {
        $ips = (self::$penyelesai ?? fn (string $h) => gethostbynamel($h) ?: [])($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $ips[0];
    }
}
