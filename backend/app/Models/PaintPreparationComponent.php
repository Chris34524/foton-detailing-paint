<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paint_preparation_id', 'material_id', 'inventory_movement_id', 'grams'])]
class PaintPreparationComponent extends Model
{
    protected function casts(): array
    {
        return ['grams' => 'decimal:2', 'density_g_per_ml_snapshot' => 'decimal:6'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(PaintPreparation::class, 'paint_preparation_id');
    }
}
