<?php

namespace App\Http\Requests\Operations;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Services\InventoryMovementService;
use Illuminate\Validation\Rule;

class InventoryMovementIndexRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', InventoryMovement::class);
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'material_id' => ['sometimes', 'integer', 'min:1'],
            'movement_type' => ['sometimes', Rule::enum(InventoryMovementType::class)],
            'area_destination' => ['sometimes', Rule::in(InventoryMovementService::AREAS)],
            'date_from' => ['sometimes', 'date_format:Y-m-d'], 'date_to' => ['sometimes', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['occurred_at', 'id', 'folio'])], 'direction' => ['sometimes', Rule::in(['asc', 'desc'])]];
    }
}
