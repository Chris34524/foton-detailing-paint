<?php

namespace App\Http\Resources;

use App\Enums\WorkOrderPriority;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit' => ['id' => $this->unit->id, 'vin' => $this->unit->vin, 'model' => $this->unit->model],
            'notes' => $this->notes,
            'work_order' => $this->whenLoaded('workOrder', fn () => $this->workOrder === null ? null : [
                'id' => $this->workOrder->id, 'status' => $this->workOrder->status->value, 'priority' => $this->workOrder->priority->value,
            ]),
            'work_order_priority_preview' => $this->when($this->relationLoaded('defects'), function (): ?array {
                $priority = WorkOrderPriority::highest($this->defects->pluck('priority'));

                return $priority === null ? null : ['value' => $priority->value, 'label' => $priority->label()];
            }),
            'diagnosed_by' => $this->diagnosedBy === null ? null : ['id' => $this->diagnosedBy->id, 'name' => $this->diagnosedBy->name],
            'defect_count' => $this->defects_count,
            'defects' => DefectResource::collection($this->whenLoaded('defects')),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
