<?php

namespace App\Models;

use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['notes'])]
class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use HasFactory;

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function diagnosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diagnosed_by');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }
}
