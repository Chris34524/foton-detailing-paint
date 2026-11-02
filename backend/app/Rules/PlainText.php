<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlainText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strip_tags($value) !== $value) {
            $fail('Ingresa texto sin etiquetas HTML.');
        }
    }
}
