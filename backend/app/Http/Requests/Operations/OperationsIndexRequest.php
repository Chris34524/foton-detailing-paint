<?php

namespace App\Http\Requests\Operations;

use App\Enums\InventoryStockStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationsIndexRequest extends OperationsRequest
{
    public function filters(array $sorts): array
    {
        $filters = $this->validated();
        if (isset($filters['sort']) && ! in_array($filters['sort'], $sorts, true)) {
            throw ValidationException::withMessages(['sort' => 'El campo de ordenación no está permitido.']);
        }

        return $filters;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'vin' => ['sometimes', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'active' => ['sometimes', 'boolean'], 'unit' => ['sometimes', 'string', 'max:24'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['created_at', 'updated_at', 'code', 'name', 'id', 'consumed_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'updated_since' => ['sometimes', 'date'], 'work_order_id' => ['sometimes', 'integer', 'min:1'],
            'stock_status' => ['sometimes', Rule::enum(InventoryStockStatus::class)],
            'status' => ['sometimes', Rule::in(['draft', 'completed'])]];
    }
}
