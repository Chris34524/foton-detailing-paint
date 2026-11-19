<?php

namespace App\Http\Requests\Operations;

use App\Rules\PlainText;

class InspectionReleaseRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('release', $this->route('inspection'));
    }

    public function rules(): array
    {
        $this->rejectUnknown(['notes']);

        return ['notes' => ['nullable', 'string', 'max:5000', new PlainText]];
    }
}
