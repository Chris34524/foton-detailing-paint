<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\WorkOrderPriority;
use App\Rules\PlainText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkOrderRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('workOrder'));
    }

    public function rules(): array
    {
        return ['priority' => ['sometimes', 'required', Rule::enum(WorkOrderPriority::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText]]
            + $this->forbiddenFields(['priority', 'notes']);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['priority', 'notes'])) {
                $validator->errors()->add('record', 'Indica al menos un campo para actualizar.');
            }
        }];
    }
}
