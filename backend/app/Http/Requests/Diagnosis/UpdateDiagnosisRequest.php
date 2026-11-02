<?php

namespace App\Http\Requests\Diagnosis;

use App\Rules\PlainText;

class UpdateDiagnosisRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('diagnosis'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText],
            'unit_id' => ['missing'], 'diagnosed_by' => ['missing'],
        ];
    }

    public function after(): array
    {
        return $this->requireUpdateFields(['notes']);
    }
}
