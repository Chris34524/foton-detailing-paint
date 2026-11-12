<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReworkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'inspection_id' => $this->inspection_id, 'work_order_id' => $this->work_order_id, 'cause' => $this->cause,
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'created_by' => $this->createdBy === null ? null : new OperatorResource($this->createdBy),
            'resolved_at' => $this->resolved_at?->toISOString(), 'created_at' => $this->created_at->toISOString()];
    }
}
