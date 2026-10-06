<?php

namespace App\Support;

use Closure;

/** Pemeriksaan anti-SSRF: semua IP hasil DNS suatu host harus publik. */
class HostAman
{
    /** @var (Closure(string): list<string>)|null  penyelesai DNS, dapat diganti di uji */
    public static ?Closure $penyelesai = null;

    /** IP publik pertama dari host; null bila DNS gagal atau ada IP pribadi/terlindung. */
    public static function ipPublik(string $host): ?string
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
