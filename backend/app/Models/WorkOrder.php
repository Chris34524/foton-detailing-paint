<?php

namespace App\Models;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['priority', 'notes'])]
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return ['status' => WorkOrderStatus::class, 'priority' => WorkOrderPriority::class, 'assigned_at' => 'datetime'];
    }

    public function processSteps(): HasMany
    {
        return $this->hasMany(WorkOrderProcessStep::class)->orderBy('position');
    }

    public function currentProcessStep(): BelongsTo
    {
        return $this->belongsTo(WorkOrderProcessStep::class, 'current_process_step_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(MaterialConsumption::class);
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(Rework::class);
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return list<WorkOrderStatus> */
    public function allowedTransitions(): array
    {
        return array_values(array_filter($this->status->transitions(),
            fn (WorkOrderStatus $status): bool => $status !== WorkOrderStatus::InProgress || $this->assigned_to !== null));
    }
}
