<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class OperationsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['code', 'name', 'label', 'description', 'unit', 'warehouse_reference', 'notes', 'external_reference', 'search', 'vin', 'rework_cause'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                if ($field === 'code' || $field === 'vin') {
                    $value = mb_strtoupper($value);
                }
                if ($field === 'unit') {
                    $value = mb_strtolower($value);
                    $value = $value === 'l' ? 'L' : $value;
                }
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    protected function rejectUnknown(array $allowed): void
    {
        $unknown = array_diff(array_keys($this->all()), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, 'Este campo no se admite en esta operación.'));
        }
    }
}
