<?php

namespace App\Http\Requests\Operations;

use App\Enums\Permission;
use App\Rules\PaintGrams;
use App\Rules\PlainText;
use App\Services\InventoryMovementService;
use Illuminate\Validation\Rule;

class PaintPreparationRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::MaterialsManage->value);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['quantity_grams', 'area_destination', 'notes', 'idempotency_key']);

        return [
            'quantity_grams' => ['required', new PaintGrams],
            'area_destination' => ['nullable', Rule::in(InventoryMovementService::AREAS)],
            'notes' => ['nullable', 'string', 'max:5000', new PlainText],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
