<?php

namespace App\Support;

/** Analisis URL tautan berkas: host, penyedia, id berkas Drive, dan jenis tautan (folder/berkas). */
class UrlBerkas
{
    /** @return array{host: string, path: string}|null  null bila bukan https yang wajar */
    public static function urai(string $url): ?array
    {
        $bagian = parse_url($url);

        if (! is_array($bagian) || ($bagian['scheme'] ?? '') !== 'https' || empty($bagian['host'])
            || isset($bagian['user']) || isset($bagian['pass'])
            || (isset($bagian['port']) && $bagian['port'] !== 443)) {
            return null;
        }

        return ['host' => strtolower($bagian['host']), 'path' => $bagian['path'] ?? '/'];
    }

    public static function hostDiizinkan(string $host): bool
    {
        foreach (config('berkas.domain_putih') as $pola) {
            if (str_starts_with($pola, '*.')) {
                if (str_ends_with($host, substr($pola, 1)) && strlen($host) > strlen($pola) - 1) {
                    return true;
                }
            } elseif ($host === $pola) {
                return true;
            }
        }

        return false;
    }

    public static function hostPemendek(string $host): bool
    {
        return in_array($host, config('berkas.pemendek'), true);
    }

    public static function penyedia(string $host): string
    {
        return match (true) {
            $host === 'drive.google.com' => 'google_drive',
            $host === 'docs.google.com' => 'google_docs',
            $host === 'onedrive.live.com', str_ends_with($host, '.sharepoint.com') => 'onedrive',
            str_ends_with($host, '.unsil.ac.id') => 'unsil',
            default => 'lainnya',
        };
    }

    public static function driveFileId(string $url): ?string
    {
        $bagian = parse_url($url);
        $host = strtolower($bagian['host'] ?? '');

        if (! in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
            return null;
        }

        if (preg_match('#/(?:file|document|spreadsheets|presentation|forms)/d/([A-Za-z0-9_-]{10,})#', $bagian['path'] ?? '', $m)) {
            return $m[1];
        }

        parse_str($bagian['query'] ?? '', $query);

        return isset($query['id']) && is_string($query['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $query['id']) ? $query['id'] : null;
    }

    public static function tautanFolder(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return (bool) preg_match('#/drive/(?:u/\d+/)?folders/|/folderview#', $path);
    }
}
