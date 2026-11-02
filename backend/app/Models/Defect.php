<?php

namespace App\Models;

use App\Enums\DefectPriority;
use Database\Factories\DefectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['defect_type_id', 'location', 'description', 'priority'])]
class Defect extends Model
{
    /** @use HasFactory<DefectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['priority' => DefectPriority::class];
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    public function defectType(): BelongsTo
    {
        return $this->belongsTo(DefectType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(DefectEvidence::class);
    }

    protected function childRouteBindingRelationshipName(mixed $childType): string
    {
        return $childType === 'evidence' ? 'evidences' : parent::childRouteBindingRelationshipName($childType);
    }
}
