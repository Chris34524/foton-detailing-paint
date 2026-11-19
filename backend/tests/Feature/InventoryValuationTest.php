<?php

namespace Tests\Feature;

use App\Models\Material;

class InventoryValuationTest extends OperationsTestCase
{
    public function test_inventory_value_covers_every_matching_product_not_only_the_visible_page(): void
    {
        $this->administrator();
        $white = Material::factory()->create(['code' => 'VALUE-WHITE', 'name' => 'Base Blanca', 'unit' => 'Kg', 'unit_cost_mxn' => '12.500000']);
        $pigment = Material::factory()->create(['code' => 'VALUE-BLACK', 'name' => 'Pigmento Negro', 'unit' => 'Kg', 'unit_cost_mxn' => '0.120000']);
        $withoutPrice = Material::factory()->create(['code' => 'VALUE-NOPRICE', 'name' => 'Base sin costo', 'unit' => 'Kg', 'unit_cost_mxn' => null]);
        Material::factory()->create(['code' => 'VALUE-ZERO', 'name' => 'Material sin existencias', 'unit' => 'Kg', 'unit_cost_mxn' => null]);
        $liquid = Material::factory()->create(['code' => 'VALUE-LIQUID', 'name' => 'Solvente', 'unit' => 'ml', 'unit_cost_mxn' => '0.185000']);

        $this->fundMaterial($white, '2');
        $this->fundMaterial($pigment, '3');
        $this->fundMaterial($withoutPrice, '1');
        $this->fundMaterial($liquid, '1000');

        $this->getJson('/api/materials?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 5)
            ->assertJsonPath('inventory_valuation.material_count', 5)
            ->assertJsonPath('inventory_valuation.priced_material_count', 3)
            ->assertJsonPath('inventory_valuation.unpriced_with_stock_count', 1)
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '210.36');

        $this->getJson('/api/materials?unit=Kg&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 4)
            ->assertJsonPath('inventory_valuation.material_count', 4)
            ->assertJsonPath('inventory_valuation.unpriced_with_stock_count', 1)
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '25.36');

        $this->getJson('/api/materials?search=VALUE-LIQUID')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '185.00')
            ->assertJsonPath('inventory_valuation.unpriced_with_stock_count', 0);

        $this->getJson('/api/materials?search=NOT-FOUND')
            ->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('inventory_valuation.material_count', 0)
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '0.00');
    }

    public function test_inventory_value_updates_when_unit_cost_or_stock_changes_without_changing_history(): void
    {
        $this->administrator();
        $material = Material::factory()->create(['unit' => 'Kg', 'unit_cost_mxn' => '100.000000']);
        $this->fundMaterial($material, '2');

        $this->getJson('/api/materials')->assertOk()
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '200.00');

        $this->patchJson('/api/materials/'.$material->id, ['unit_cost_mxn' => '125.000000'])->assertOk();
        $this->getJson('/api/materials')->assertOk()
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '250.00');

        $this->patchJson('/api/materials/'.$material->id, ['unit_cost_mxn' => null])->assertOk();
        $this->getJson('/api/materials')->assertOk()
            ->assertJsonPath('inventory_valuation.unpriced_with_stock_count', 1)
            ->assertJsonPath('inventory_valuation.total_cost_mxn', '0.00');
    }
}
