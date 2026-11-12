<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PaintGrams implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value))
            || ! preg_match('/^\\d{1,12}(?:\\.\\d{1,2})?$/D', (string) $value)
            || bccomp((string) $value, '0', 2) <= 0) {
            $fail('Indica un gramaje positivo con máximo dos decimales, por ejemplo 125.35.');
        }
    }
}
