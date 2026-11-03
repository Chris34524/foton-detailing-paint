<?php

namespace App\Http\Resources;

use App\Enums\WorkOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status->value, 'status_label' => $this->status->label(),
            'priority' => $this->priority->value, 'priority_label' => $this->priority->label(), 'notes' => $this->notes,
            'is_terminal' => $this->status->isTerminal(),
            'can_unassign' => ! $this->status->isTerminal() && $this->status !== WorkOrderStatus::InProgress,
            'allowed_status_transitions' => array_map(fn (WorkOrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()], $this->resource->allowedTransitions()),
            'unit' => ['id' => $this->diagnosis->unit->id, 'vin' => $this->diagnosis->unit->vin, 'model' => $this->diagnosis->unit->model],
            'diagnosis' => $this->diagnosis->relationLoaded('defects') ? new DiagnosisResource($this->diagnosis)
                : ['id' => $this->diagnosis->id, 'created_at' => $this->diagnosis->created_at->toISOString(), 'defect_count' => $this->diagnosis->defects_count],
            'assigned_to' => $this->assignedTo === null ? null : new OperatorResource($this->assignedTo),
            'assigned_by' => $this->assignedBy === null ? null : new OperatorResource($this->assignedBy),
            'assigned_at' => $this->assigned_at?->toISOString(),
            'created_by' => $this->createdBy === null ? null : new OperatorResource($this->createdBy),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
