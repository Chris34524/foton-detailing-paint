<?php

namespace App\Http\Requests\Operations;

use App\Models\MaterialConsumption;
use App\Rules\PlainText;

class MaterialConsumptionRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [MaterialConsumption::class, $this->route('workOrder')]);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['material_id', 'quantity', 'work_order_process_step_id', 'notes', 'external_reference']);

        return ['material_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'work_order_process_step_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000', new PlainText], 'external_reference' => ['nullable', 'string', 'max:120', new PlainText]];
    }
}
