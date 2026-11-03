<?php

namespace App\Http\Requests\Operations;

use App\Models\InspectionChecklist;
use App\Models\Material;
use App\Models\Process;
use App\Rules\PlainText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogRequest extends OperationsRequest
{
    private function modelClass(): string
    {
        return match (true) {
            $this->routeIs('processes.*') => Process::class,
            $this->routeIs('materials.*') => Material::class,
            default => InspectionChecklist::class,
        };
    }

    public function authorize(): bool
    {
        $record = $this->route('process') ?? $this->route('material') ?? $this->route('checklist');

        return $record ? $this->user()->can('update', $record) : $this->user()->can('create', $this->modelClass());
    }

    public function rules(): array
    {
        $class = $this->modelClass();
        $record = $this->route('process') ?? $this->route('material') ?? $this->route('checklist');
        $required = $record ? 'sometimes' : 'required';
        $rules = ['name' => [$required, 'required', 'string', 'max:160', new PlainText],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000', new PlainText], 'is_active' => ['sometimes', 'boolean']];
        if ($class !== InspectionChecklist::class) {
            $table = (new $class)->getTable();
            $rules['code'] = [$required, 'required', 'string', 'max:80', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique($table, 'code')->ignore($record?->id)];
        }
        if ($class === Material::class) {
            $rules['unit'] = [$required, 'required', 'string', 'max:24', 'regex:/^[\pL][\pL0-9.\/-]*$/u'];
            $rules['warehouse_reference'] = ['sometimes', 'nullable', 'string', 'max:120', new PlainText];
        }
        $this->rejectUnknown(array_keys($rules));
        if ($record && $this->all() === []) {
            throw ValidationException::withMessages(['record' => 'Indica al menos un campo para actualizar.']);
        }

        return $rules;
    }
}
