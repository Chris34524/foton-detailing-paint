<?php

namespace App\Enums;

enum InventoryStockStatus: string
{
    case Critical = 'critical';
    case Reorder = 'reorder';
    case Ok = 'ok';
    case Overstock = 'overstock';
    case NoParameters = 'no_parameters';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'CRÍTICO',self::Reorder => 'REORDEN',self::Ok => 'OK',self::Overstock => 'SOBRESTOCK',self::NoParameters => 'SIN PARÁMETROS'
        };
    }

    public function action(): string
    {
        return match ($this) {
            self::Critical => 'COMPRAR / REABASTECER',self::Reorder => 'PROGRAMAR COMPRA',self::Ok => 'SIN ACCIÓN INMEDIATA',self::Overstock => 'DETENER / REVISAR COMPRA',self::NoParameters => 'COMPLETAR MÍNIMO / MÁXIMO'
        };
    }

    public static function calculate(string $stock, ?string $minimum, ?string $maximum): self
    {
        if ($minimum === null || $maximum === null) {
            return self::NoParameters;
        }
        if (bccomp($stock, $minimum, 6) < 0) {
            return self::Critical;
        }
        if (bccomp($stock, $maximum, 6) > 0) {
            return self::Overstock;
        }
        if (bccomp($stock, bcmul($minimum, '1.2', 7), 7) <= 0) {
            return self::Reorder;
        }

        return self::Ok;
    }

    public static function sql(): string
    {
        return "CASE WHEN materials.minimum_stock IS NULL OR materials.maximum_stock IS NULL THEN 'no_parameters' WHEN COALESCE(inventory_balances.quantity,0) < materials.minimum_stock THEN 'critical' WHEN COALESCE(inventory_balances.quantity,0) > materials.maximum_stock THEN 'overstock' WHEN COALESCE(inventory_balances.quantity,0) <= materials.minimum_stock * 1.2 THEN 'reorder' ELSE 'ok' END";
    }
}
