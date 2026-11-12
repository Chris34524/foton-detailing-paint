<?php

namespace App\Http\Requests\Operations;

use App\Enums\Permission;

class ProcessSequenceRequest extends OperationsRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ProcessesManage);
    }

    public function rules(): array
    {
        $this->rejectUnknown(['process_ids']);

        return ['process_ids' => ['present', 'array', 'max:100'], 'process_ids.*' => ['required', 'integer', 'min:1', 'distinct:strict']];
    }
}
