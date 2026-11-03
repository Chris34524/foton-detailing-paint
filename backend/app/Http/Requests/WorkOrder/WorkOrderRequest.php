<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

abstract class WorkOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['notes', 'search'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    protected function forbiddenFields(array $allowed): array
    {
        $fields = ['diagnosis_id', 'unit_id', 'status', 'priority', 'notes', 'assigned_to', 'assigned_by', 'assigned_at', 'created_by'];

        return array_fill_keys(array_diff($fields, $allowed), ['missing']);
    }
}
