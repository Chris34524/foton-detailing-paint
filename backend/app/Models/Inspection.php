<?php

namespace App\Models;

use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => InspectionStatus::class, 'result' => InspectionResult::class, 'completed_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklist::class, 'inspection_checklist_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItemResult::class)->orderBy('sort_order_snapshot')->orderBy('id');
    }

    public function rework(): HasOne
    {
        return $this->hasOne(Rework::class);
    }

    public function release(): HasOne
    {
        return $this->hasOne(UnitRelease::class);
    }
}
