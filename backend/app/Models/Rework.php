<?php

namespace App\Models;

use App\Enums\ReworkStatus;
use Database\Factories\ReworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class Rework extends Model
{
    /** @use HasFactory<ReworkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => ReworkStatus::class, 'resolved_at' => 'datetime'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
