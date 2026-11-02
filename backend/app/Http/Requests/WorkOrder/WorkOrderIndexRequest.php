<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Validation\Rule;

class WorkOrderIndexRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', WorkOrder::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(WorkOrderStatus::class)],
            'priority' => ['sometimes', Rule::enum(WorkOrderPriority::class)],
            'assignment' => ['sometimes', Rule::in(['assigned', 'unassigned'])],
            'assigned_to' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', Rule::in(['created_at', 'updated_at', 'status', 'priority'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
