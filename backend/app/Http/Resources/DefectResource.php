<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'diagnosis_id' => $this->diagnosis_id,
            'defect_type' => new DefectTypeResource($this->defectType),
            'location' => $this->location, 'description' => $this->description,
            'priority' => $this->priority->value, 'priority_label' => $this->priority->label(),
            'created_by' => $this->createdBy === null ? null : ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'evidences' => DefectEvidenceResource::collection($this->whenLoaded('evidences')),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
