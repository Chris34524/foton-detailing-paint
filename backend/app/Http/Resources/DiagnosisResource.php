<?php

namespace App\Http\Resources;

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
            'diagnosed_by' => $this->diagnosedBy === null ? null : ['id' => $this->diagnosedBy->id, 'name' => $this->diagnosedBy->name],
            'defect_count' => $this->defects_count,
            'defects' => DefectResource::collection($this->whenLoaded('defects')),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
