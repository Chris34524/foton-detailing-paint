<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'description' => $this->description, 'is_active' => $this->is_active,
            'created_by' => $this->whenLoaded('createdBy', fn () => new OperatorResource($this->createdBy)),
            'items' => InspectionChecklistItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
