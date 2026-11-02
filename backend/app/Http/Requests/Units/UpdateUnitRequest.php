<?php

namespace App\Http\Requests\Units;

use App\Enums\UnitStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUnitRequest extends UnitRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('unit'));
    }

    public function rules(): array
    {
        return [
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'required', Rule::enum(UnitStatus::class)],
            'current_location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'vin' => ['missing'],
            'created_by' => ['missing'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['model', 'status', 'current_location'])) {
                $validator->errors()->add('unit', 'Indica al menos un campo para actualizar.');
            }
        }];
    }
}
