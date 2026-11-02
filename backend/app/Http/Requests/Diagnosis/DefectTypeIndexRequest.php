<?php

namespace App\Http\Requests\Diagnosis;

use App\Models\DefectType;

class DefectTypeIndexRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', DefectType::class)
            && (! $this->boolean('include_inactive') || $this->user()->can('viewInactive', DefectType::class));
    }

    public function rules(): array
    {
        return [
            'include_inactive' => ['nullable', 'boolean'], 'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
