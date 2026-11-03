<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialConsumptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->workOrder->diagnosis->unit;

        return ['id' => $this->id, 'material' => ['id' => $this->material_id, 'code' => $this->material_code_snapshot, 'name' => $this->material_name_snapshot, 'unit' => $this->unit_snapshot],
            'work_order_id' => $this->work_order_id, 'unit' => ['id' => $unit->id, 'vin' => $unit->vin, 'model' => $unit->model],
            'process_step' => $this->processStep === null ? null : new WorkOrderProcessStepResource($this->processStep),
            'quantity' => $this->quantity, 'notes' => $this->notes, 'external_reference' => $this->external_reference,
            'consumed_by' => $this->consumedBy === null ? null : new OperatorResource($this->consumedBy),
            'consumed_at' => $this->consumed_at->toISOString(), 'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
