<?php

namespace App\Models;

use Database\Factories\WorkOrderProcessStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class WorkOrderProcessStep extends Model
{
    /** @use HasFactory<WorkOrderProcessStepFactory> */
    use HasFactory;

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
