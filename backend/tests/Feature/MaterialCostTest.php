<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Material;

class MaterialCostTest extends OperationsTestCase
{
    public function test_cost_is_optional_and_inventory_value_uses_the_current_canonical_unit(): void
    {
        $this->administrator();
        $id = $this->postJson('/api/materials', [
            'name' => 'Base blanca',
            'unit' => 'Kg',
        ])->assertCreated()->assertJsonPath('data.unit_cost_mxn', null)
            ->assertJsonPath('data.stock_value_mxn', null)->json('data.id');

        $material = Material::findOrFail($id);
        $this->fundMaterial($material, '2.5');

        $this->patchJson('/api/materials/'.$id, ['unit_cost_mxn' => '125.500000'])
            ->assertOk()->assertJsonPath('data.unit_cost_mxn', '125.500000')
            ->assertJsonPath('data.stock_value_mxn', '313.75');

        $this->getJson('/api/materials/'.$id)->assertOk()
            ->assertJsonPath('data.unit_cost_mxn', '125.500000')
            ->assertJsonPath('data.stock_value_mxn', '313.75');
        $this->getJson('/api/materials?search=PROD-001')->assertOk()
            ->assertJsonPath('data.0.unit_cost_mxn', '125.500000')
            ->assertJsonPath('data.0.stock_value_mxn', '313.75');

        $this->patchJson('/api/materials/'.$id, ['unit_cost_mxn' => '0'])
            ->assertOk()->assertJsonPath('data.unit_cost_mxn', '0.000000')
            ->assertJsonPath('data.stock_value_mxn', '0.00');
        $this->patchJson('/api/materials/'.$id, ['unit_cost_mxn' => null])
            ->assertOk()->assertJsonPath('data.unit_cost_mxn', null)
            ->assertJsonPath('data.stock_value_mxn', null);
        $this->assertSame('2.500000', $material->fresh()->inventoryBalance->quantity);
    }

    public function test_cost_validates_exact_mxn_decimal_values_and_rejects_forged_fields(): void
    {
        $this->administrator();
        foreach (['-1', 'NaN', '1e2', '1.1234567', '1000000000000', '2,50', 1.25, []] as $invalid) {
            $this->postJson('/api/materials', [
                'name' => 'Producto de prueba',
                'unit' => 'ml',
                'unit_cost_mxn' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('unit_cost_mxn');
        }

        $this->postJson('/api/materials', [
            'name' => 'Pigmento',
            'unit' => 'gr',
            'unit_cost_mxn' => '0.000001',
        ])->assertCreated()->assertJsonPath('data.unit_cost_mxn', '0.000001');
    }

    public function test_finished_paint_cost_can_change_but_its_recipe_identity_cannot(): void
    {
        $this->administrator();
        $raw = Material::factory()->create(['unit' => 'gr', 'unit_cost_mxn' => '0.500000']);
        $formula = $this->postJson('/api/paint-formulas', [
            'vehicle_model' => 'FOTON S5',
            'color_name' => 'Blanco Perla',
            'color_code' => 'WP01',
            'components' => [['material_id' => $raw->id, 'grams' => '100.00']],
        ])->assertCreated()->assertJsonPath('data.product.unit_cost_mxn', null)->json('data');

        $productId = $formula['product']['id'];
        $productCode = $formula['product']['code'];

        $this->patchJson('/api/materials/'.$productId, ['unit_cost_mxn' => '0.850000'])
            ->assertOk()->assertJsonPath('data.unit_cost_mxn', '0.850000')
            ->assertJsonPath('data.kind', 'finished_paint')
            ->assertJsonPath('data.code', $productCode);
        $this->getJson('/api/paint-formulas/'.$formula['id'])->assertOk()
            ->assertJsonPath('data.product.unit_cost_mxn', '0.850000');

        $this->patchJson('/api/materials/'.$productId, [
            'unit_cost_mxn' => '1.00',
            'name' => 'Pintura alterada',
        ])->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->patchJson('/api/materials/'.$productId, ['name' => 'Pintura alterada'])
            ->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->assertSame('0.850000', Material::findOrFail($productId)->unit_cost_mxn);

        $this->withPermissions(Permission::MaterialsView);
        $this->patchJson('/api/materials/'.$productId, ['unit_cost_mxn' => '2.00'])->assertForbidden();
    }
}
