<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Vin implements ValidationRule
{
    public static function normalize(string $vin): string
    {
        return strtoupper(trim($vin));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/\A[A-HJ-NPR-Z0-9]{17}\z/', $value) !== 1) {
            $fail('El VIN debe tener 17 letras o números, sin I, O ni Q.');
        }
    }
}
