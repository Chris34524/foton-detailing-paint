<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vin' => $this->vin,
            'model' => $this->model,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'current_location' => $this->current_location,
            'registered_by' => $this->registeredBy === null ? null : [
                'id' => $this->registeredBy->id,
                'name' => $this->registeredBy->name,
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
