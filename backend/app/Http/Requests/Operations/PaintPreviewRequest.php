<?php

namespace App\Http\Requests\Operations;

use App\Enums\Permission;
use App\Rules\PaintGrams;

class PaintPreviewRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::MaterialsView->value);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['quantity_grams']);

        return ['quantity_grams' => ['required', new PaintGrams]];
    }
}
