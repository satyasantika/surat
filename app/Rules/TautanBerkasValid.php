<?php

namespace App\Rules;

use App\Support\UrlBerkas;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tautan berkas (STANDAR-TEKNIS §1a.2): https, domain daftar putih, bukan pemendek URL,
 * dan bukan tautan folder bila yang diminta satu berkas.
 */
class TautanBerkasValid implements ValidationRule
{
    public function __construct(private readonly bool $satuBerkas = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 2048) {
            $fail('Tautan tidak valid.');

            return;
        }

        $urai = UrlBerkas::urai($value);

        if ($urai === null) {
            $fail('Tautan harus berupa alamat https yang sah, tanpa kredensial.');

            return;
        }

        if (UrlBerkas::hostPemendek($urai['host'])) {
            $fail('Pemendek URL tidak diperbolehkan; gunakan tautan asli berkas.');

            return;
        }

        if (! UrlBerkas::hostDiizinkan($urai['host'])) {
            $fail('Domain tautan tidak diizinkan. Gunakan Google Drive/Docs, OneDrive, atau domain unsil.ac.id.');

            return;
        }

        if ($this->satuBerkas && UrlBerkas::tautanFolder($value)) {
            $fail('Tautan folder tidak diterima; bagikan tautan satu berkas.');
        }
    }
}
