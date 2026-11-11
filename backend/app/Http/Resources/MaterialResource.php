<?php

namespace App\Http\Resources;

use App\Enums\MaterialUnit;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'unit' => $this->unit,
            'warehouse_reference' => $this->warehouse_reference, 'kind' => $this->kind, 'is_active' => $this->is_active,
            'location' => $this->location, 'supplier' => $this->supplier, 'notes' => $this->notes,
            'unit_supported' => MaterialUnit::normalize($this->unit) !== null,
            'has_inventory_history' => (bool) $this->inventory_movements_exists || (bool) $this->material_consumptions_exists,
            ...app(InventoryStockService::class)->summary($this->resource),
            'created_by' => $this->whenLoaded('createdBy', fn () => new OperatorResource($this->createdBy)),
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
