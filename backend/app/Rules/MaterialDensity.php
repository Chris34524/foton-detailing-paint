<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaterialDensity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value))
            || ! preg_match('/^\\d{1,4}(?:\\.\\d{1,6})?$/D', (string) $value)
            || bccomp((string) $value, '0', 6) <= 0) {
            $fail('Indica una densidad positiva en g/ml, con hasta 4 enteros y 6 decimales, por ejemplo 1.250.');
        }
    }
}
