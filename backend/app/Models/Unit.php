<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Rules\Vin;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Fillable(['vin', 'model', 'status', 'current_location'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $attributes = ['status' => UnitStatus::Registered->value];

    protected function casts(): array
    {
        return ['status' => UnitStatus::class];
    }

    protected function vin(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Vin::normalize($value));
    }

    protected static function booted(): void
    {
        static::saving(function (Unit $unit): void {
            if ($unit->exists && $unit->isDirty('vin')) {
                throw ValidationException::withMessages(['vin' => 'El VIN no puede modificarse después del registro.']);
            }
            Validator::make(['vin' => $unit->vin], ['vin' => ['required', new Vin]])->validate();
        });
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
