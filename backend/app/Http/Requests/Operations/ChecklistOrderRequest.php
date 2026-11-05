<?php

namespace App\Http\Requests\Operations;

class ChecklistOrderRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('checklist'));
    }

    public function rules(): array
    {
        $this->rejectUnknown(['item_ids']);

        return ['item_ids' => ['present', 'array', 'max:1000'], 'item_ids.*' => ['required', 'integer', 'min:1', 'distinct:strict']];
    }
}
