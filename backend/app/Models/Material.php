<?php

namespace App\Models;

use App\Enums\MaterialUnit;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name', 'description', 'unit', 'minimum_stock', 'maximum_stock', 'location', 'supplier', 'notes', 'warehouse_reference', 'is_active'])]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper(trim($value)));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'minimum_stock' => 'decimal:6', 'maximum_stock' => 'decimal:6', 'density_g_per_ml' => 'decimal:6'];
    }

    protected function unit(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => MaterialUnit::normalize($value)?->value ?? trim($value));
    }

    public function inventoryBalance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
