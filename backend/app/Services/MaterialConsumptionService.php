<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialConsumptionService
{
    public function consume(WorkOrder $order, User $actor, array $data): MaterialConsumption
    {
        return DB::transaction(function () use ($order, $actor, $data): MaterialConsumption {
            $current = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($current->status->isTerminal()) {
                throw ValidationException::withMessages(['work_order' => 'No puedes registrar consumo en una orden completada o cancelada.']);
            }
            $material = Material::query()->lockForUpdate()->find($data['material_id']);
            if ($material === null || ! $material->is_active) {
                throw ValidationException::withMessages(['material_id' => 'Selecciona un material activo.']);
            }
            $stepId = $data['work_order_process_step_id'] ?? null;
            if ($stepId !== null && ! $current->processSteps()->whereKey($stepId)->exists()) {
                throw ValidationException::withMessages(['work_order_process_step_id' => 'El proceso debe pertenecer a esta orden de trabajo.']);
            }
            $consumption = new MaterialConsumption;
            $consumption->material_id = $material->id;
            $consumption->work_order_id = $current->id;
            $consumption->work_order_process_step_id = $stepId;
            $consumption->material_code_snapshot = $material->code;
            $consumption->material_name_snapshot = $material->name;
            $consumption->unit_snapshot = $material->unit;
            $consumption->quantity = $data['quantity'];
            $consumption->notes = $data['notes'] ?? null;
            $consumption->external_reference = $data['external_reference'] ?? null;
            $consumption->consumed_by = $actor->id;
            $consumption->consumed_at = now();
            $consumption->save();

            return $consumption;
        }, 3);
    }
}
