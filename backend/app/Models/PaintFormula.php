<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_material_id', 'vehicle_model', 'color_name', 'color_code', 'version', 'total_grams', 'is_current', 'created_by'])]
class PaintFormula extends Model
{
    protected function casts(): array
    {
        return ['total_grams' => 'decimal:2', 'is_current' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(PaintFormulaComponent::class);
    }

    public function preparations(): HasMany
    {
        return $this->hasMany(PaintPreparation::class);
    }
}
