<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InventoryDecimal implements ValidationRule
{
    public function __construct(private bool $positive = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\\d{1,12}(?:\\.\\d{1,6})?$/D', (string) $value)
            || bccomp((string) $value, '0', 6) < ($this->positive ? 1 : 0)) {
            $fail('Usa una cantidad decimal '.($this->positive ? 'positiva' : 'no negativa').' de hasta 12 enteros y 6 decimales, enviada como texto.');
        }
    }
}
