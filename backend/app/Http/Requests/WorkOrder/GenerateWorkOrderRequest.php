<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\WorkOrder;
use App\Rules\PlainText;

class GenerateWorkOrderRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [WorkOrder::class, $this->route('diagnosis')]);
    }

    public function rules(): array
    {
        return ['notes' => ['nullable', 'string', 'max:5000', new PlainText]] + $this->forbiddenFields(['notes']);
    }
}
