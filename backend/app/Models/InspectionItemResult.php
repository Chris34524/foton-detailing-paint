<?php

namespace App\Models;

use App\Enums\InspectionItemCompliance;
use Database\Factories\InspectionItemResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class InspectionItemResult extends Model
{
    /** @use HasFactory<InspectionItemResultFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_required_snapshot' => 'boolean', 'result' => InspectionItemCompliance::class];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklistItem::class, 'checklist_item_id');
    }
}
