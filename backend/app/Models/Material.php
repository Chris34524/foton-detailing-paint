<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'description', 'unit', 'warehouse_reference', 'is_active'])]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
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
}
