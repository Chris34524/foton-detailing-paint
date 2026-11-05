<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionItemResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'checklist_item_id' => $this->checklist_item_id, 'item_code_snapshot' => $this->item_code_snapshot, 'item_label_snapshot' => $this->item_label_snapshot, 'item_description_snapshot' => $this->item_description_snapshot, 'sort_order_snapshot' => $this->sort_order_snapshot, 'is_required_snapshot' => $this->is_required_snapshot, 'comment' => $this->comment, 'result' => $this->result?->value, 'result_label' => $this->result?->label(),
            'created_by' => $this->whenLoaded('createdBy', fn () => new OperatorResource($this->createdBy)),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
