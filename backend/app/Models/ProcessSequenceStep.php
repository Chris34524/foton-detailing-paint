<?php

namespace App\Models;

use Database\Factories\ProcessSequenceStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class ProcessSequenceStep extends Model
{
    /** @use HasFactory<ProcessSequenceStepFactory> */
    use HasFactory;

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
