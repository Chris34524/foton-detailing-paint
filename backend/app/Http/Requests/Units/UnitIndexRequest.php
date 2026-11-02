<?php

namespace App\Http\Requests\Units;

use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Rules\Vin;
use Illuminate\Validation\Rule;

class UnitIndexRequest extends UnitRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Unit::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'vin' => ['nullable', 'string', new Vin],
            'model' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UnitStatus::class)],
            'location' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', Rule::in(['vin', 'model', 'status', 'current_location', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
