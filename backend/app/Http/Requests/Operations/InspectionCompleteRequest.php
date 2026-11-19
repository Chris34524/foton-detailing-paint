<?php

namespace App\Http\Requests\Operations;

use App\Rules\PlainText;

class InspectionCompleteRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('complete', $this->route('inspection'));
    }

    public function rules(): array
    {
        $this->rejectUnknown(['rework_cause']);

        return ['rework_cause' => ['nullable', 'string', 'max:5000', new PlainText]];
    }
}
