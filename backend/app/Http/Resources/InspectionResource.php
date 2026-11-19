<?php

namespace App\Http\Resources;

use App\Enums\InspectionItemCompliance;
use App\Services\InspectionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->workOrder->diagnosis->unit;

        return ['id' => $this->id, 'work_order_id' => $this->work_order_id, 'work_order_status' => $this->workOrder->status->value,
            'unit' => ['id' => $unit->id, 'vin' => $unit->vin, 'model' => $unit->model, 'status' => $unit->status->value, 'status_label' => $unit->status->label()],
            'checklist' => ['id' => $this->inspection_checklist_id, 'name' => $this->checklist_name_snapshot],
            'status' => $this->status->value, 'status_label' => $this->status->label(), 'result' => $this->result?->value, 'result_label' => $this->result?->label(),
            'notes' => $this->notes, 'inspected_by' => $this->inspectedBy === null ? null : new OperatorResource($this->inspectedBy),
            'item_result_options' => $this->when($this->relationLoaded('items'), fn (): array => array_map(fn (InspectionItemCompliance $result): array => ['value' => $result->value, 'label' => $result->label()], InspectionItemCompliance::cases())),
            'items' => InspectionItemResultResource::collection($this->whenLoaded('items')),
            'rework' => $this->rework === null ? null : new ReworkResource($this->rework),
            'release' => $this->release === null ? null : new UnitReleaseResource($this->release),
            'release_checks' => $this->when($this->relationLoaded('items'), fn () => app(InspectionService::class)->releaseChecks($this->resource)),
            'completed_at' => $this->completed_at?->toISOString(), 'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
