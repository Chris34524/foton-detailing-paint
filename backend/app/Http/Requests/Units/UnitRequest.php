<?php

namespace App\Http\Requests\Units;

use App\Rules\Vin;
use Illuminate\Foundation\Http\FormRequest;

abstract class UnitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['vin', 'model', 'current_location', 'search', 'location'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized = $field === 'vin' ? Vin::normalize($value) : trim($value);
                $this->merge([$field => $normalized === '' ? null : $normalized]);
            }
        }
    }
}
