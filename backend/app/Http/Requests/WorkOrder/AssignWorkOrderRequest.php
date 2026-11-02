<?php

namespace App\Http\Requests\WorkOrder;

class AssignWorkOrderRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('workOrder'));
    }

    public function rules(): array
    {
        return ['assigned_to' => ['present', 'nullable', 'integer', 'min:1']] + $this->forbiddenFields(['assigned_to']);
    }
}
