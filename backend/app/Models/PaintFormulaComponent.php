<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paint_formula_id', 'material_id', 'material_code_snapshot', 'material_name_snapshot', 'grams'])]
class PaintFormulaComponent extends Model
{
    protected function casts(): array
    {
        return ['grams' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(PaintFormula::class, 'paint_formula_id');
    }
}
