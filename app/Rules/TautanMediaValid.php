<?php

namespace App\Rules;

use App\Support\UrlBerkas;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Tautan media publik (Instagram/YouTube) untuk LPJ dan galeri: https dan domain daftar putih media. */
class TautanMediaValid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $urai = is_string($value) && strlen($value) <= 2048 ? UrlBerkas::urai($value) : null;

        if ($urai === null) {
            $fail('Tautan harus berupa alamat https yang sah, tanpa kredensial.');

            return;
        }

        foreach (config('berkas.domain_media') as $pola) {
            $cocok = str_starts_with($pola, '*.')
                ? str_ends_with($urai['host'], substr($pola, 1)) && strlen($urai['host']) > strlen($pola) - 1
                : $urai['host'] === $pola;

            if ($cocok) {
                return;
            }
        }

        $fail('Gunakan tautan Instagram atau YouTube.');
    }
}
