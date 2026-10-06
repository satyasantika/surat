<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SurelDomainUnsil implements ValidationRule
{
    /**
     * @param  list<string>|null  $domain  null = semua domain dari config('unsil.domain_surel')
     */
    public function __construct(private readonly ?array $domain = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $diizinkan = $this->domain ?? config('unsil.domain_surel');
        $domain = is_string($value) && str_contains($value, '@')
            ? strtolower(substr(strrchr($value, '@'), 1))
            : '';

        if (! in_array($domain, array_map('strtolower', $diizinkan), true)) {
            $fail('Surel harus memakai domain '.implode(' atau ', $diizinkan).'.');
        }
    }
}
