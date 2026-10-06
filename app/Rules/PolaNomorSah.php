<?php

namespace App\Rules;

use App\Support\PolaNomor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PolaNomorSah implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PolaNomor::valid((string) $value)) {
            $fail('Pola tidak sah: gunakan token yang dikenal dan tepat satu {urut}.');
        }
    }
}
