<?php

namespace App\Http\Requests\Operations;

use App\Enums\InventoryMovementType;
use App\Enums\MaterialUnit;
use App\Models\InventoryMovement;
use App\Rules\InventoryDecimal;
use App\Rules\PlainText;
use App\Services\InventoryMovementService;
use Illuminate\Validation\Rule;

class InventoryMovementRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        $type = is_string($this->input('movement_type')) ? InventoryMovementType::tryFrom($this->input('movement_type')) : null;

        return $this->user()->can('create', [InventoryMovement::class, $type]);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['material_id', 'movement_type', 'quantity_original', 'unit_original', 'area_destination', 'notes', 'idempotency_key']);

        return [
            'material_id' => ['required', 'integer', 'min:1'], 'movement_type' => ['required', Rule::enum(InventoryMovementType::class)],
            'quantity_original' => ['required', new InventoryDecimal],
            'unit_original' => ['required', Rule::enum(MaterialUnit::class)],
            'area_destination' => ['nullable', Rule::in(InventoryMovementService::AREAS)],
            'notes' => ['nullable', 'string', 'max:5000', new PlainText],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
