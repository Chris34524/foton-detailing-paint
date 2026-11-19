<?php

namespace App\Http\Requests\Operations;

use App\Enums\InspectionItemCompliance;
use App\Models\Inspection;
use App\Rules\PlainText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InspectionRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->route('inspection') ? $this->user()->can('update', $this->route('inspection')) : $this->user()->can('create', Inspection::class);
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            $this->rejectUnknown(['inspection_checklist_id', 'notes']);

            return ['inspection_checklist_id' => ['required', 'integer', 'min:1'], 'notes' => ['nullable', 'string', 'max:5000', new PlainText]];
        }
        $this->rejectUnknown(['notes', 'items']);
        if ($this->all() === []) {
            throw ValidationException::withMessages(['record' => 'Indica respuestas o notas para actualizar.']);
        }

        return ['notes' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText],
            'items' => ['sometimes', 'array', 'min:1', 'max:1000'],
            'items.*' => ['required', 'array:id,result,comment'],
            'items.*.id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'items.*.result' => ['sometimes', 'nullable', Rule::enum(InspectionItemCompliance::class)],
            'items.*.comment' => ['sometimes', 'nullable', 'string', 'max:2000', new PlainText]];
    }
}
