<?php

namespace App\Http\Requests\Diagnosis;

use App\Enums\DefectPriority;
use App\Rules\PlainText;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class UpdateDefectRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('diagnosis'));
    }

    public function rules(): array
    {
        return [
            'defect_type_id' => ['sometimes', 'required', 'integer',
                Rule::exists('defect_types', 'id')->where(fn (Builder $query) => $query->where(
                    fn (Builder $types) => $types->where('is_active', true)->orWhere('id', $this->route('defect')->defect_type_id),
                ))],
            'location' => ['sometimes', 'required', 'string', 'max:160', new PlainText],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText],
            'priority' => ['sometimes', 'required', Rule::enum(DefectPriority::class)],
            'created_by' => ['missing'], 'diagnosis_id' => ['missing'],
        ];
    }

    public function after(): array
    {
        return $this->requireUpdateFields(['defect_type_id', 'location', 'description', 'priority']);
    }
}
