<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable([])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    protected $hidden = ['request_fingerprint', 'idempotency_key', 'opening_material_id'];

    protected function casts(): array
    {
        return ['movement_type' => InventoryMovementType::class, 'quantity_original' => 'decimal:6', 'quantity_equivalent' => 'decimal:6', 'signed_quantity' => 'decimal:6', 'stock_before' => 'decimal:6', 'stock_after' => 'decimal:6', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Confirmed inventory movements are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Confirmed inventory movements cannot be deleted.');
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function consumption(): HasOne
    {
        return $this->hasOne(MaterialConsumption::class);
    }
}
