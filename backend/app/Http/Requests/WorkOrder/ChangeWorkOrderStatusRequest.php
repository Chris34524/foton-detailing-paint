<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\WorkOrderStatus;
use Illuminate\Validation\Rule;

class ChangeWorkOrderStatusRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('workOrder'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(WorkOrderStatus::class)]] + $this->forbiddenFields(['status']);
    }
}
