<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaintPreparationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paint_formula_id' => $this->paint_formula_id,
            'version' => $this->formula->version,
            'vehicle_model' => $this->formula->vehicle_model,
            'color_name' => $this->formula->color_name,
            'color_code' => $this->formula->color_code,
            'product' => ['id' => $this->product->id, 'code' => $this->product->code, 'name' => $this->product->name, 'unit' => $this->product->unit],
            'quantity_grams' => $this->quantity_grams,
            'output_folio' => $this->outputMovement?->folio,
            'area_destination' => $this->area_destination,
            'notes' => $this->notes,
            'prepared_by' => $this->preparedBy?->name,
            'prepared_at' => $this->prepared_at->toISOString(),
            'components' => $this->components->map(fn ($item): array => [
                'material_id' => $item->material_id,
                'code' => $item->movement->material_code_snapshot,
                'name' => $item->movement->material_name_snapshot,
                'grams' => $item->grams,
                'movement_folio' => $item->movement->folio,
            ])->values()->all(),
        ];
    }
}
