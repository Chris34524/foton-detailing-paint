<?php

namespace App\Services;

use App\Enums\MaterialUnit;
use App\Models\Material;
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

    /**
     * Convert a paint recipe's grams to the material's canonical stock unit.
     * Volumetric consumptions are rounded UP at ledger precision to avoid
     * understating raw-material usage when density produces recurring decimals.
     */
    public function gramsForStock(string $grams, Material $material): string
    {
        $unit = MaterialUnit::normalize($material->unit);
        if ($unit?->family() === 'mass') {
            return $this->convert($grams, 'gr', $material->unit);
        }
        if ($unit?->family() !== 'volume' || $material->density_g_per_ml === null
            || bccomp($material->density_g_per_ml, '0', 6) <= 0) {
            throw ValidationException::withMessages(['components' => 'El material requiere unidad gr/Kg o densidad positiva (g/ml) si se almacena en ml/L.']);
        }

        $millilitres = bcdiv($this->amount($grams), $material->density_g_per_ml, 18);
        $exact = $unit === MaterialUnit::Litre ? bcdiv($millilitres, '1000', 18) : $millilitres;
        $truncated = bcadd($exact, '0', 6);
        $quantity = bccomp($exact, $truncated, 18) > 0 ? bcadd($truncated, '0.000001', 6) : $truncated;
        if (bccomp($quantity, '0', 6) <= 0 || bccomp($quantity, self::MAX, 6) > 0) {
            throw ValidationException::withMessages(['components' => 'La conversión por densidad excede el rango permitido de existencias.']);
        }

        return $quantity;
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
