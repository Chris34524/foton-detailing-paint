<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case Entry = 'entry';
    case Exit = 'exit';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'APERTURA',self::Entry => 'ENTRADA',self::Exit => 'SALIDA',self::AdjustmentIn => 'AJUSTE +',self::AdjustmentOut => 'AJUSTE -'
        };
    }

    public function increases(): bool
    {
        return in_array($this, [self::OpeningBalance, self::Entry, self::AdjustmentIn], true);
    }

    public function isAdjustment(): bool
    {
        return in_array($this, [self::AdjustmentIn, self::AdjustmentOut], true);
    }

    public function totalColumn(): string
    {
        return match ($this) {
            self::OpeningBalance => 'opening_quantity',self::Entry => 'total_entries',self::Exit => 'total_exits',self::AdjustmentIn => 'total_adjustments_in',self::AdjustmentOut => 'total_adjustments_out'
        };
    }
}
