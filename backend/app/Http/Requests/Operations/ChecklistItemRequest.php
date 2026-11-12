<?php

namespace App\Http\Requests\Operations;

use App\Rules\PlainText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChecklistItemRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('checklist'));
    }

    public function rules(): array
    {
        $this->rejectUnknown(['code', 'label', 'description', 'is_active', 'is_required']);
        $item = $this->route('item');
        if ($item && $this->all() === []) {
            throw ValidationException::withMessages(['record' => 'Indica al menos un campo para actualizar.']);
        }
        $required = $item ? 'sometimes' : 'required';

        return ['code' => [$required, 'required', 'string', 'max:80', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
            Rule::unique('inspection_checklist_items', 'code')->where('inspection_checklist_id', $this->route('checklist')->id)->ignore($item?->id)],
            'label' => [$required, 'required', 'string', 'max:300', new PlainText], 'description' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText],
            'is_active' => ['sometimes', 'boolean'], 'is_required' => ['sometimes', 'boolean']];
    }
}
