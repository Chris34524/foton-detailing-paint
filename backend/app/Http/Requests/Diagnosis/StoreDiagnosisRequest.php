<?php

namespace App\Http\Requests\Diagnosis;

use App\Models\Diagnosis;
use App\Rules\PlainText;

class StoreDiagnosisRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Diagnosis::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000', new PlainText],
            'unit_id' => ['missing'], 'diagnosed_by' => ['missing'],
        ];
    }
}
