<?php

namespace App\Http\Requests\Diagnosis;

use App\Rules\PlainText;
use App\Rules\UniqueDefectTypeName;

class UpdateDefectTypeRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('defectType'));
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:120', new PlainText, new UniqueDefectTypeName($this->route('defectType')->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000', new PlainText],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return $this->requireUpdateFields(['name', 'description', 'is_active']);
    }
}
