<?php

namespace App\Http\Requests\Operations;

use App\Enums\Permission;
use App\Rules\PaintGrams;
use App\Rules\PlainText;
use Illuminate\Validation\Rule;

class PaintFormulaRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::MaterialsManage->value);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['vehicle_model', 'color_name', 'color_code', 'components']);

        return [
            'vehicle_model' => ['required', 'string', 'max:100', new PlainText],
            'color_name' => ['required', 'string', 'max:160', new PlainText],
            'color_code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9_\\-\\/]*$/D'],
            'components' => ['required', 'array', 'min:1', 'max:30'],
            'components.*' => ['required', 'array:material_id,grams'],
            'components.*.material_id' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('materials', 'id')],
            'components.*.grams' => ['required', new PaintGrams],
        ];
    }
}
