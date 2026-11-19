<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionChecklistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'label' => $this->label, 'description' => $this->description, 'sort_order' => $this->sort_order, 'is_required' => $this->is_required, 'is_active' => $this->is_active,
            'created_by' => $this->whenLoaded('createdBy', fn () => new OperatorResource($this->createdBy)),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
