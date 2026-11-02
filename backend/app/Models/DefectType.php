<?php

namespace App\Models;

use Database\Factories\DefectTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_active'])]
class DefectType extends Model
{
    /** @use HasFactory<DefectTypeFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }
}
