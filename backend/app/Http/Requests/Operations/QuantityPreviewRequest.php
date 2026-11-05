<?php

namespace App\Http\Requests\Operations;

use App\Enums\InventoryMovementType;
use App\Enums\MaterialUnit;
use App\Enums\Permission;
use App\Rules\InventoryDecimal;
use Illuminate\Validation\Rule;

class QuantityPreviewRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::MaterialsView);
    }

    public function rules(): array
    {
        return ['quantity_original' => ['required', new InventoryDecimal], 'unit_original' => ['required', Rule::enum(MaterialUnit::class)],
            'movement_type' => ['sometimes', Rule::enum(InventoryMovementType::class)]];
    }
}
