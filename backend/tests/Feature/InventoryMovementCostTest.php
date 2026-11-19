<?php

namespace Tests\Feature;

use App\Models\Material;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class InventoryMovementCostTest extends OperationsTestCase
{
    private function movement(Material $material, string $type, string $quantity, string $unit, ?string $key = null): array
    {
        return [
            'material_id' => $material->id,
            'movement_type' => $type,
            'quantity_original' => $quantity,
            'unit_original' => $unit,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ];
    }

    public function test_cost_is_frozen_when_movement_is_confirmed_and_retries_cannot_reprice_history(): void
    {
        $this->administrator();
        $material = Material::factory()->create(['unit' => 'L', 'unit_cost_mxn' => '125.500000']);
        $opening = $this->postJson('/api/inventory-movements', $this->movement($material, 'opening_balance', '5', 'L'))
            ->assertCreated()->assertJsonPath('data.unit_cost_mxn_snapshot', '125.500000')
            ->assertJsonPath('data.total_cost_mxn', '627.500000');
        $key = (string) Str::uuid();
        $exit = $this->movement($material, 'exit', '750', 'ml', $key);
        $this->postJson('/api/inventory-movements', $exit)->assertCreated()
            ->assertJsonPath('data.quantity_equivalent', '0.750000')
            ->assertJsonPath('data.unit_cost_mxn_snapshot', '125.500000')
            ->assertJsonPath('data.total_cost_mxn', '94.125000');

        $this->patchJson('/api/materials/'.$material->id, ['unit_cost_mxn' => '150.000000'])->assertOk();
        $this->postJson('/api/inventory-movements', $exit)->assertOk()
            ->assertJsonPath('idempotent_replay', true)->assertJsonPath('data.total_cost_mxn', '94.125000');
        $this->getJson('/api/inventory-movements/'.$opening->json('data.id'))->assertOk()
            ->assertJsonPath('data.total_cost_mxn', '627.500000');
        $this->postJson('/api/inventory-movements', $this->movement($material, 'entry', '1', 'L'))
            ->assertCreated()->assertJsonPath('data.unit_cost_mxn_snapshot', '150.000000')
            ->assertJsonPath('data.total_cost_mxn', '150.000000');
        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_period_totals_use_every_filtered_movement_even_with_pagination_and_missing_costs(): void
    {
        $this->administrator();
        $priced = Material::factory()->create(['unit' => 'L', 'unit_cost_mxn' => '10.000000']);
        $unpriced = Material::factory()->create(['unit' => 'L']);

        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00'));
        $this->postJson('/api/inventory-movements', $this->movement($priced, 'opening_balance', '5', 'L'))
            ->assertCreated()->assertJsonPath('data.total_cost_mxn', '50.000000');

        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:30:00'));
        $this->postJson('/api/inventory-movements', $this->movement($priced, 'exit', '750', 'ml'))
            ->assertCreated()->assertJsonPath('data.total_cost_mxn', '7.500000');

        $this->travelTo(CarbonImmutable::parse('2026-11-12 08:30:00'));
        $this->patchJson('/api/materials/'.$priced->id, ['unit_cost_mxn' => '20.000000'])->assertOk();
        $this->postJson('/api/inventory-movements', $this->movement($priced, 'entry', '2', 'L'))
            ->assertCreated()->assertJsonPath('data.total_cost_mxn', '40.000000');
        $this->postJson('/api/inventory-movements', $this->movement($unpriced, 'entry', '1', 'L'))
            ->assertCreated()->assertJsonPath('data.total_cost_mxn', null)
            ->assertJsonPath('data.unit_cost_mxn_snapshot', null);
        $this->travelBack();

        $this->getJson('/api/inventory-movements?date_from=2026-11-10&date_to=2026-11-12&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 4)
            ->assertJsonPath('cost_summary.movement_count', 4)
            ->assertJsonPath('cost_summary.with_cost_count', 3)
            ->assertJsonPath('cost_summary.without_cost_count', 1)
            ->assertJsonPath('cost_summary.inbound_total_mxn', '90.000000')
            ->assertJsonPath('cost_summary.outbound_total_mxn', '7.500000')
            ->assertJsonPath('cost_summary.recorded_total_mxn', '97.500000');

        $this->getJson('/api/inventory-movements?date_from=2026-11-11&date_to=2026-11-11&movement_type=exit')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('cost_summary.without_cost_count', 0)
            ->assertJsonPath('cost_summary.inbound_total_mxn', '0.000000')
            ->assertJsonPath('cost_summary.outbound_total_mxn', '7.500000');

        $this->getJson('/api/inventory-movements?date_from=2026-11-12&date_to=2026-11-12&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)->assertJsonPath('cost_summary.movement_count', 2)
            ->assertJsonPath('cost_summary.without_cost_count', 1)
            ->assertJsonPath('cost_summary.recorded_total_mxn', '40.000000');

        $this->getJson('/api/inventory-movements?date_from=2026-11-13&date_to=2026-11-14')
            ->assertOk()->assertJsonPath('cost_summary.movement_count', 0)
            ->assertJsonPath('cost_summary.recorded_total_mxn', '0.000000');
    }

    public function test_cost_snapshot_cannot_be_supplied_from_client_and_zero_price_differs_from_unknown(): void
    {
        $this->administrator();
        $material = Material::factory()->create(['unit_cost_mxn' => '0.000000']);
        $base = $this->movement($material, 'entry', '10', $material->unit);
        foreach (['unit_cost_mxn_snapshot', 'total_cost_mxn'] as $field) {
            $this->postJson('/api/inventory-movements', [...$base, $field => '999.00'])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/inventory-movements', $base)->assertCreated()
            ->assertJsonPath('data.unit_cost_mxn_snapshot', '0.000000')
            ->assertJsonPath('data.total_cost_mxn', '0.000000');
        $this->getJson('/api/inventory-movements')->assertOk()
            ->assertJsonPath('cost_summary.with_cost_count', 1)
            ->assertJsonPath('cost_summary.without_cost_count', 0);
    }
}
