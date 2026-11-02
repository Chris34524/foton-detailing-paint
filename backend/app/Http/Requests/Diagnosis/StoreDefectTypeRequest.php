<?php

namespace App\Http\Requests\Diagnosis;

use App\Models\DefectType;
use App\Rules\PlainText;
use App\Rules\UniqueDefectTypeName;

class StoreDefectTypeRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DefectType::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:120', new PlainText, new UniqueDefectTypeName],
            'description' => ['nullable', 'string', 'max:2000', new PlainText],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
