<?php

namespace App\Rules;

use App\Models\JenisNaskah;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TemplatNaskahAda implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! JenisNaskah::templatAda(is_string($value) ? $value : null)) {
            $fail('Templat tidak ditemukan: gunakan nama naskah.{kode} dengan berkas di resources/views/naskah/.');
        }
    }
}
