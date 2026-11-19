<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'unit_id' => $this->unit_id, 'inspection_id' => $this->inspection_id, 'notes' => $this->notes,
            'released_by' => $this->releasedBy === null ? null : new OperatorResource($this->releasedBy),
            'released_at' => $this->released_at->toISOString()];
    }
}
