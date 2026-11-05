<?php

namespace App\Models;

use Database\Factories\ProcessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class Process extends Model
{
    /** @use HasFactory<ProcessFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper(trim($value)));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sequenceStep(): HasOne
    {
        return $this->hasOne(ProcessSequenceStep::class);
    }
}
