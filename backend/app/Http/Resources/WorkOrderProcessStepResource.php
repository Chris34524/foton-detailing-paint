<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderProcessStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['step_id' => $this->id, 'process_id' => $this->process_id, 'code' => $this->process_code_snapshot, 'name' => $this->process_name_snapshot, 'position' => $this->position];
    }
}
