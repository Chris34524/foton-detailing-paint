<?php

namespace App\Services;

use App\Enums\InventoryStockStatus;
use App\Models\InventoryBalance;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryStockService
{
    public function query(): Builder
    {
        return Material::query()->leftJoin('inventory_balances', 'inventory_balances.material_id', '=', 'materials.id')
            ->select('materials.*')->selectRaw('COALESCE(inventory_balances.quantity,0) as stock_actual_cache')
            ->with(['createdBy', 'inventoryBalance'])->withExists(['inventoryMovements', 'materialConsumptions']);
    }

    public function summary(Material $material): array
    {
        $balance = $material->inventoryBalance;
        $stock = $balance?->quantity ?? '0.000000';
        $status = InventoryStockStatus::calculate($stock, $material->minimum_stock, $material->maximum_stock);
        $percentage = $material->maximum_stock !== null && bccomp($material->maximum_stock, '0', 6) > 0
            ? bcdiv(bcmul($stock, '100', 8), $material->maximum_stock, 2) : null;

        return ['stock_actual' => $stock, 'minimum_stock' => $material->minimum_stock, 'maximum_stock' => $material->maximum_stock,
            'stock_percentage' => $percentage, 'stock_status' => $status->value, 'stock_status_label' => $status->label(), 'recommended_action' => $status->action(),
            'initial_stock' => $balance?->opening_quantity ?? '0.000000', 'entries' => $balance?->total_entries ?? '0.000000',
            'exits' => $balance?->total_exits ?? '0.000000', 'adjustments_in' => $balance?->total_adjustments_in ?? '0.000000',
            'adjustments_out' => $balance?->total_adjustments_out ?? '0.000000'];
    }

    public function ledgerTotals(Material $material): array
    {
        $totals = array_fill_keys(['quantity', ...InventoryBalance::TOTALS], '0.000000');
        foreach ($material->inventoryMovements()->orderBy('id')->cursor() as $movement) {
            $totals['quantity'] = bcadd($totals['quantity'], $movement->signed_quantity, 6);
            $column = $movement->movement_type->totalColumn();
            $totals[$column] = bcadd($totals[$column], $movement->quantity_equivalent, 6);
        }

        return $totals;
    }

    public function reconcile(Material $material, bool $repair = false): array
    {
        return DB::transaction(function () use ($material, $repair): array {
            $current = Material::query()->lockForUpdate()->findOrFail($material->id);
            $balance = InventoryBalance::query()->where('material_id', $current->id)->lockForUpdate()->first();
            $totals = $this->ledgerTotals($current);
            $matches = true;
            foreach ($totals as $column => $value) {
                if (bccomp($balance?->{$column} ?? '0', $value, 6) !== 0) {
                    $matches = false;
                }
            }
            if ($repair && ! $matches) {
                $balance ??= new InventoryBalance;
                $balance->material_id = $current->id;
                foreach ($totals as $column => $value) {
                    $balance->{$column} = $value;
                }
                $balance->save();
                $current->touch();
            }

            return ['reconciled' => $matches || $repair, 'repaired' => $repair && ! $matches, 'ledger' => $totals, 'cached_stock' => $balance?->quantity ?? '0.000000'];
        }, 3);
    }
}
