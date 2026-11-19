<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class InventoryValuationService
{
    /**
     * Sum today's priced stock across ALL materials matching the catalog filters,
     * before pagination. Price-less stock is excluded and explicitly disclosed.
     *
     * @return array{material_count:int, priced_material_count:int, unpriced_with_stock_count:int, total_cost_mxn:string}
     */
    public function summarize(Builder $query): array
    {
        $totals = (clone $query)->reorder()->select([])->selectRaw('
            COUNT(*) AS material_count,
            COALESCE(SUM(CASE WHEN materials.unit_cost_mxn IS NOT NULL THEN 1 ELSE 0 END), 0) AS priced_material_count,
            COALESCE(SUM(CASE WHEN materials.unit_cost_mxn IS NULL
                AND COALESCE(inventory_balances.quantity, 0) > 0 THEN 1 ELSE 0 END), 0) AS unpriced_with_stock_count,
            COALESCE(SUM(CASE WHEN materials.unit_cost_mxn IS NOT NULL
                THEN ROUND(COALESCE(inventory_balances.quantity, 0) * materials.unit_cost_mxn, 2)
                ELSE 0 END), 0) AS total_cost_mxn
        ')->first();

        return [
            'material_count' => (int) $totals->material_count,
            'priced_material_count' => (int) $totals->priced_material_count,
            'unpriced_with_stock_count' => (int) $totals->unpriced_with_stock_count,
            'total_cost_mxn' => bcadd((string) $totals->total_cost_mxn, '0', 2),
        ];
    }
}
