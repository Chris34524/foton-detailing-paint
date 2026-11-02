<?php

namespace App\Http\Requests\Diagnosis;

use App\Enums\DefectPriority;
use App\Rules\PlainText;
use Illuminate\Validation\Rule;

class StoreDefectRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('diagnosis'));
    }

    public function rules(): array
    {
        return [
            'defect_type_id' => ['required', 'integer', Rule::exists('defect_types', 'id')->where('is_active', true)],
            'location' => ['required', 'string', 'max:160', new PlainText],
            'description' => ['nullable', 'string', 'max:5000', new PlainText],
            'priority' => ['required', Rule::enum(DefectPriority::class)],
            'created_by' => ['missing'], 'diagnosis_id' => ['missing'],
        ];
    }
}
