<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\Permission;

class OperatorIndexRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkOrdersAssign);
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
