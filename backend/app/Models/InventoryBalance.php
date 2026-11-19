<?php

namespace App\Models;

use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class InventoryBalance extends Model
{
    /** @use HasFactory<InventoryBalanceFactory> */
    use HasFactory;

    public const TOTALS = ['opening_quantity', 'total_entries', 'total_exits', 'total_adjustments_in', 'total_adjustments_out'];

    protected $attributes = ['quantity' => '0', 'opening_quantity' => '0', 'total_entries' => '0', 'total_exits' => '0', 'total_adjustments_in' => '0', 'total_adjustments_out' => '0'];

    protected function casts(): array
    {
        return array_fill_keys(['quantity', ...self::TOTALS], 'decimal:6');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
