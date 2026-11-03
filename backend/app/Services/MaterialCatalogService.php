<?php

namespace App\Services;

use App\Enums\MaterialUnit;
use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialCatalogService
{
    private function validateParameters(Material $material): void
    {
        if ($material->minimum_stock !== null && $material->maximum_stock !== null && bccomp($material->maximum_stock, $material->minimum_stock, 6) < 0) {
            throw ValidationException::withMessages(['maximum_stock' => 'El máximo debe ser mayor o igual al mínimo.']);
        }
        if (MaterialUnit::normalize($material->unit) === null) {
            throw ValidationException::withMessages(['unit' => 'Selecciona una unidad del catálogo de inventario.']);
        }
    }

    public function create(User $actor, array $data): Material
    {
        return DB::transaction(function () use ($actor, $data): Material {
            $record = new Material($data);
            $record->created_by = $actor->id;
            $this->validateParameters($record);
            $record->save();

            return $record;
        }, 3);
    }

    public function update(Material $material, array $data): Material
    {
        return DB::transaction(function () use ($material, $data): Material {
            $current = Material::query()->lockForUpdate()->findOrFail($material->id);
            $previousUnit = $current->unit;
            $current->fill($data);
            if ($current->unit !== $previousUnit && ($current->inventoryMovements()->exists() || $current->materialConsumptions()->exists())) {
                throw ValidationException::withMessages(['unit' => 'La unidad canónica no puede cambiar cuando el material tiene historial.']);
            }
            $this->validateParameters($current);
            $current->save();

            return $current;
        }, 3);
    }
}
