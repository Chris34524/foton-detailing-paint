<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'folio' => $this->folio,
            'material' => ['id' => $this->material_id, 'code' => $this->material_code_snapshot, 'name' => $this->material_name_snapshot, 'unit' => $this->unit_snapshot],
            'movement_type' => $this->movement_type->value, 'movement_type_label' => $this->movement_type->label(),
            'quantity_original' => $this->quantity_original, 'unit_original' => $this->unit_original, 'quantity_equivalent' => $this->quantity_equivalent,
            'signed_quantity' => $this->signed_quantity, 'stock_before' => $this->stock_before, 'stock_after' => $this->stock_after,
            'area_destination' => $this->area_destination, 'notes' => $this->notes, 'occurred_at' => $this->occurred_at->toISOString(),
            'performed_by' => $this->performedBy === null ? null : new OperatorResource($this->performedBy),
            'source_kind' => $this->source_kind,
            'consumption' => $this->whenLoaded('consumption', fn (): array => [
                'id' => $this->consumption->id, 'work_order_id' => $this->consumption->work_order_id,
                'vin' => $this->consumption->workOrder->diagnosis->unit->vin, 'process_step_id' => $this->consumption->work_order_process_step_id]),
        ];
    }
}
