<?php

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class DiagnosisRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['notes', 'name', 'description', 'location', 'search'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    protected function requireUpdateFields(array $fields): array
    {
        return [function (Validator $validator) use ($fields): void {
            if (! $this->hasAny($fields)) {
                $validator->errors()->add('record', 'Indica al menos un campo para actualizar.');
            }
        }];
    }
}
