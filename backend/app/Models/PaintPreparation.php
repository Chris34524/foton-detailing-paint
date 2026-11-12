<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['paint_formula_id', 'product_material_id', 'quantity_grams', 'output_movement_id', 'idempotency_key', 'request_fingerprint', 'area_destination', 'notes', 'prepared_by', 'prepared_at'])]
class PaintPreparation extends Model
{
    protected $hidden = ['idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['quantity_grams' => 'decimal:2', 'prepared_at' => 'datetime'];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(PaintFormula::class, 'paint_formula_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function outputMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'output_movement_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PaintPreparationComponent::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
