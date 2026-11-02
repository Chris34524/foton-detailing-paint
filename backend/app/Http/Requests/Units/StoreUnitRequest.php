<?php

namespace App\Http\Requests\Units;

use App\Models\Unit;
use App\Rules\Vin;

class StoreUnitRequest extends UnitRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Unit::class);
    }

    public function rules(): array
    {
        return [
            'vin' => ['bail', 'required', 'string', 'size:17', new Vin, 'unique:units,vin'],
            'model' => ['nullable', 'string', 'max:100'],
            'current_location' => ['nullable', 'string', 'max:120'],
            'status' => ['missing'],
            'created_by' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return ['vin.unique' => 'Este VIN ya está registrado.'];
    }
}
