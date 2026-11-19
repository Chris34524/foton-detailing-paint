<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class InventoryMovementCostSummaryService
{
    /**
     * Cost totals are calculated on the full filtered set, before pagination.
     * Missing historical costs stay unknown rather than being inferred from today's price.
     *
     * @return array<string, int|string>
     */
    public function summarize(Builder $query): array
    {
        $totals = (clone $query)->toBase()->selectRaw("
            COUNT(*) AS movement_count,
            SUM(CASE WHEN total_cost_mxn IS NULL THEN 1 ELSE 0 END) AS without_cost_count,
            COALESCE(SUM(total_cost_mxn), 0) AS recorded_total_mxn,
            COALESCE(SUM(CASE WHEN movement_type IN ('opening_balance', 'entry', 'adjustment_in') THEN total_cost_mxn ELSE 0 END), 0) AS inbound_total_mxn,
            COALESCE(SUM(CASE WHEN movement_type IN ('exit', 'adjustment_out') THEN total_cost_mxn ELSE 0 END), 0) AS outbound_total_mxn
        ")->first();

        $totalCount = (int) $totals->movement_count;
        $withoutCost = (int) $totals->without_cost_count;

        return [
            'movement_count' => $totalCount,
            'with_cost_count' => $totalCount - $withoutCost,
            'without_cost_count' => $withoutCost,
            'recorded_total_mxn' => (string) $totals->recorded_total_mxn,
            'inbound_total_mxn' => (string) $totals->inbound_total_mxn,
            'outbound_total_mxn' => (string) $totals->outbound_total_mxn,
        ];
    }
}
