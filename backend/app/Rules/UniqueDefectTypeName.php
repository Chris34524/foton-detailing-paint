<?php

namespace App\Rules;

use App\Models\DefectType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

class UniqueDefectTypeName implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && DefectType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->when($this->ignoreId, fn (Builder $query): Builder => $query->whereKeyNot($this->ignoreId))->exists()) {
            $fail('Este tipo de defecto ya está registrado.');
        }
    }
}
