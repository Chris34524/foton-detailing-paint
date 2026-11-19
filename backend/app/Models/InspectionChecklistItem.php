<?php

namespace App\Models;

use Database\Factories\InspectionChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'label', 'description', 'is_required', 'is_active'])]
class InspectionChecklistItem extends Model
{
    /** @use HasFactory<InspectionChecklistItemFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true, 'is_required' => true];

    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper(trim($value)));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_required' => 'boolean'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklist::class, 'inspection_checklist_id');
    }
}
