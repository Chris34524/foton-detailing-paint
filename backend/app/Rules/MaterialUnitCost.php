<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaterialUnitCost implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value))
            || ! preg_match('/^\\d{1,12}(?:\\.\\d{1,6})?$/D', (string) $value)
            || bccomp((string) $value, '999999999999.999999', 6) > 0) {
            $fail('Indica un costo unitario en MXN mayor o igual a cero, con máximo seis decimales.');
        }
    }
}
