<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessSequenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'position' => $this->position, 'process' => new ProcessResource($this->process)];
    }
}
