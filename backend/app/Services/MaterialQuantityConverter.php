<?php

namespace App\Services;

use App\Enums\MaterialUnit;
use App\Rules\InventoryDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MaterialQuantityConverter
{
    public const MAX = '999999999999.999999';

    public function amount(mixed $value, bool $positive = true, string $field = 'quantity_original'): string
    {
        Validator::make([$field => $value], [$field => ['required', new InventoryDecimal($positive)]])->validate();

        return bcadd((string) $value, '0', 6);
    }

    public function convert(mixed $value, string $original, string $canonical): string
    {
        $quantity = $this->amount($value);
        $from = MaterialUnit::normalize($original);
        $to = MaterialUnit::normalize($canonical);
        if ($from === null || $to === null || $from->family() !== $to->family()) {
            throw ValidationException::withMessages(['unit_original' => 'La unidad de movimiento no es compatible con la unidad de inventario.']);
        }
        $exact = bcdiv(bcmul($quantity, $from->factor(), 12), $to->factor(), 12);
        $converted = bcadd($exact, '0', 6);
        if (bccomp($exact, $converted, 12) !== 0 || bccomp($converted, self::MAX, 6) > 0 || bccomp($converted, '0', 6) <= 0) {
            throw ValidationException::withMessages(['quantity_original' => 'La equivalencia debe ser positiva, representable con 6 decimales y dentro del límite de inventario.']);
        }

        return $converted;
    }
}
