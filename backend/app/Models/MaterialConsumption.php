<?php

namespace App\Models;

use Database\Factories\MaterialConsumptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class MaterialConsumption extends Model
{
    /** @use HasFactory<MaterialConsumptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'consumed_at' => 'datetime'];
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function processStep(): BelongsTo
    {
        return $this->belongsTo(WorkOrderProcessStep::class, 'work_order_process_step_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }
}
