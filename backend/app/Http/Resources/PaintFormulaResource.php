<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaintFormulaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'product_material_id' => $this->product_material_id,
            'vehicle_model' => $this->vehicle_model,
            'color_name' => $this->color_name,
            'color_code' => $this->color_code,
            'version' => $this->version,
            'total_grams' => $this->total_grams,
            'is_current' => $this->is_current,
            'created_at' => $this->created_at->toISOString(),
            'product' => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'unit' => $product->unit,
                'stock_actual' => $product->inventoryBalance?->quantity ?? '0.000000',
                'is_active' => $product->is_active,
            ],
            'components' => $this->components->map(fn ($component): array => [
                'id' => $component->id,
                'material_id' => $component->material_id,
                'code' => $component->material_code_snapshot,
                'name' => $component->material_name_snapshot,
                'grams' => $component->grams,
                'unit' => $component->material->unit,
                'is_active' => $component->material->is_active,
                'stock_actual' => $component->material->inventoryBalance?->quantity ?? '0.000000',
            ])->values()->all(),
        ];
    }
}
