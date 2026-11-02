<?php

namespace App\Http\Requests\Diagnosis;

use App\Models\Diagnosis;
use Illuminate\Validation\Rule;

class DiagnosisIndexRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Diagnosis::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'unit_id' => ['nullable', 'integer', 'min:1', 'exists:units,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'id'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
