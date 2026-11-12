<?php

namespace App\Http\Requests\Operations;

use App\Enums\MaterialUnit;
use App\Models\MaterialConsumption;
use App\Rules\InventoryDecimal;
use App\Rules\PlainText;
use App\Services\InventoryMovementService;
use Illuminate\Validation\Rule;

class MaterialConsumptionRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [MaterialConsumption::class, $this->route('workOrder')]);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['material_id', 'quantity', 'unit_original', 'area_destination', 'work_order_process_step_id', 'notes', 'external_reference', 'idempotency_key']);

        return ['material_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', new InventoryDecimal],
            'unit_original' => ['sometimes', Rule::enum(MaterialUnit::class)], 'area_destination' => ['nullable', Rule::in(InventoryMovementService::AREAS)],
            'work_order_process_step_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000', new PlainText], 'external_reference' => ['nullable', 'string', 'max:120', new PlainText],
            'idempotency_key' => ['required', 'uuid']];
    }
}
