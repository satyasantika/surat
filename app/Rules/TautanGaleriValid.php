<?php

namespace App\Rules;

use App\Support\UrlBerkas;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tautan galeri menurut tipe: foto = berkas Drive/unsil (bukan folder); video = YouTube atau berkas Drive;
 * instagram = tautan instagram.com saja (tidak pernah di-embed).
 */
class TautanGaleriValid implements ValidationRule
{
    public function __construct(private readonly ?string $tipe) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($this->tipe, ['foto', 'video', 'instagram'], true)) {
            $fail('Tipe galeri tidak dikenal.');

            return;
        }

        $urai = is_string($value) && strlen($value) <= 2048 ? UrlBerkas::urai($value) : null;

        if ($urai === null) {
            $fail('Tautan harus berupa alamat https yang sah, tanpa kredensial.');

            return;
        }

        $host = $urai['host'];
        $ok = match ($this->tipe) {
            'instagram' => $host === 'instagram.com' || str_ends_with($host, '.instagram.com'),
            'video' => $this->youtube($host) || $this->berkasDrive($value, $host),
            default => $this->berkasDrive($value, $host),
        };

        if (! $ok) {
            $fail(match ($this->tipe) {
                'instagram' => 'Gunakan tautan instagram.com.',
                'video' => 'Gunakan tautan YouTube atau berkas Google Drive.',
                default => 'Gunakan tautan satu berkas foto dari Google Drive atau domain unsil.ac.id.',
            });
        }
    }

    private function youtube(string $host): bool
    {
        return $host === 'youtu.be' || $host === 'youtube.com' || str_ends_with($host, '.youtube.com');
    }

    private function berkasDrive(string $url, string $host): bool
    {
        return UrlBerkas::hostDiizinkan($host) && ! UrlBerkas::hostPemendek($host) && ! UrlBerkas::tautanFolder($url);
    }
}
