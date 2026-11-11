<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\PaintFormula;
use App\Models\WorkOrder;
use Illuminate\Support\Str;

class PaintFormulationTest extends OperationsTestCase
{
    private function formulaPayload(array $components): array
    {
        return [
            'vehicle_model' => 'FOTON S5',
            'color_name' => 'Blanco Perla',
            'color_code' => 'WP01',
            'components' => $components,
        ];
    }

    private function recipe(Material $white, Material $resin): array
    {
        return $this->formulaPayload([
            ['material_id' => $white->id, 'grams' => '350.50'],
            ['material_id' => $resin->id, 'grams' => '149.50'],
        ]);
    }

    public function test_creating_preparing_and_retrying_a_formula_produces_atomic_inventory_movements(): void
    {
        $this->administrator();
        $white = Material::factory()->create(['unit' => 'gr', 'name' => 'Base blanca']);
        $resin = Material::factory()->create(['unit' => 'Kg', 'name' => 'Resina']);
        $this->fundMaterial($white, '1000');
        $this->fundMaterial($resin, '1');

        $id = $this->postJson('/api/paint-formulas', $this->recipe($white, $resin))
            ->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.total_grams', '500.00')
            ->assertJsonPath('data.product.code', 'PROD-001')->json('data.id');
        $product = PaintFormula::findOrFail($id)->product;
        $this->assertSame('finished_paint', $product->kind);
        $this->assertSame('gr', $product->unit);
        $this->getJson('/api/paint-formulas')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/api/paint-formulas/'.$id.'/preview', ['quantity_grams' => '1000.00'])
            ->assertOk()->assertJsonPath('total_ingredient_grams', '1000.00')
            ->assertJsonPath('components.0.grams', '701.00')
            ->assertJsonPath('components.1.grams', '299.00')
            ->assertJsonPath('can_confirm', true);

        $payload = ['quantity_grams' => '1000.00', 'area_destination' => 'Pintura 1', 'notes' => 'Lote de prueba',
            'idempotency_key' => (string) Str::uuid()];
        $result = $this->postJson('/api/paint-formulas/'.$id.'/prepare', $payload)->assertCreated()
            ->assertJsonPath('data.output_folio', 'MOV-0005')->assertJsonCount(2, 'data.components')
            ->assertJsonPath('data.quantity_grams', '1000.00');
        $this->assertSame('1000.000000', $product->fresh()->inventoryBalance->quantity);
        $this->assertSame('299.000000', $white->fresh()->inventoryBalance->quantity);
        $this->assertSame('0.701000', $resin->fresh()->inventoryBalance->quantity);
        $this->assertDatabaseCount('paint_preparations', 1);
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->assertSame(3, InventoryMovement::query()->where('source_kind', 'paint_preparation')->count());
        $this->postJson('/api/paint-formulas/'.$id.'/prepare', $payload)->assertOk()
            ->assertJsonPath('idempotent_replay', true)->assertJsonPath('data.id', $result->json('data.id'));
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->postJson('/api/paint-formulas/'.$id.'/prepare', [...$payload, 'quantity_grams' => '500.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->getJson('/api/paint-preparations')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/materials/'.$product->id.'/stock')->assertOk()
            ->assertJsonPath('reconciliation.reconciled', true);
        $this->getJson('/api/materials/'.$white->id.'/stock')->assertOk()
            ->assertJsonPath('reconciliation.reconciled', true);
    }

    public function test_lot_creation_rolls_back_if_any_raw_material_is_insufficient(): void
    {
        $this->administrator();
        $white = Material::factory()->create(['unit' => 'gr']);
        $resin = Material::factory()->create(['unit' => 'gr']);
        $this->fundMaterial($white, '1000');
        $this->fundMaterial($resin, '100');
        $formulaId = $this->postJson('/api/paint-formulas', $this->recipe($white, $resin))
            ->assertCreated()->json('data.id');
        $this->postJson('/api/paint-formulas/'.$formulaId.'/preview', ['quantity_grams' => '500.00'])
            ->assertOk()->assertJsonPath('can_confirm', false);
        $this->postJson('/api/paint-formulas/'.$formulaId.'/prepare', [
            'quantity_grams' => '500.00', 'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('components');
        $this->assertDatabaseCount('paint_preparations', 0);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('1000.000000', $white->fresh()->inventoryBalance->quantity);
        $this->assertSame('100.000000', $resin->fresh()->inventoryBalance->quantity);
    }

    public function test_versions_preserve_old_ingredients_but_only_latest_can_be_prepared(): void
    {
        $this->administrator();
        $first = Material::factory()->create(['unit' => 'gr']);
        $second = Material::factory()->create(['unit' => 'gr']);
        $id = $this->postJson('/api/paint-formulas', $this->recipe($first, $second))
            ->assertCreated()->json('data.id');
        $revised = $this->formulaPayload([
            ['material_id' => $first->id, 'grams' => '300.00'],
            ['material_id' => $second->id, 'grams' => '200.00'],
        ]);
        $new = $this->postJson('/api/paint-formulas/'.$id.'/versions', $revised)
            ->assertCreated()->assertJsonPath('data.version', 2)->json('data.id');
        $this->assertSame(PaintFormula::findOrFail($id)->product_material_id, PaintFormula::findOrFail($new)->product_material_id);
        $this->getJson('/api/paint-formulas/'.$id)->assertOk()->assertJsonPath('data.is_current', false)
            ->assertJsonPath('data.components.0.grams', '350.50');
        $this->getJson('/api/paint-formulas/'.$id.'/versions')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/paint-formulas/'.$id.'/prepare', [
            'quantity_grams' => '500.00', 'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('formula');
    }

    public function test_formula_rejects_invalid_components_and_direct_finished_paint_stock_entries(): void
    {
        $this->administrator();
        $raw = Material::factory()->create(['unit' => 'gr']);
        $other = Material::factory()->create(['unit' => 'L']);
        $this->postJson('/api/paint-formulas', $this->formulaPayload([
            ['material_id' => $other->id, 'grams' => '1.25'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('components');
        $this->postJson('/api/paint-formulas', $this->formulaPayload([
            ['material_id' => $raw->id, 'grams' => '0.001'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('components.0.grams');
        $this->postJson('/api/paint-formulas', $this->formulaPayload([
            ['material_id' => $raw->id, 'grams' => '1.00'],
            ['material_id' => $raw->id, 'grams' => '1.00'],
        ]))->assertUnprocessable();
        $id = $this->postJson('/api/paint-formulas', $this->formulaPayload([
            ['material_id' => $raw->id, 'grams' => '500.00'],
        ]))->assertCreated()->json('data.id');
        $productId = PaintFormula::findOrFail($id)->product_material_id;
        $this->postJson('/api/inventory-movements', [
            'material_id' => $productId, 'movement_type' => 'entry', 'quantity_original' => '100',
            'unit_original' => 'gr', 'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('material_id');
        $this->postJson('/api/inventory-movements', [
            'material_id' => $productId, 'movement_type' => 'exit', 'quantity_original' => '10',
            'unit_original' => 'gr', 'idempotency_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('material_id');
        $this->patchJson('/api/materials/'.$productId, ['name' => 'Cambio'])->assertUnprocessable();
    }

    public function test_largest_remainder_keeps_exact_two_decimal_mass_and_finished_paint_is_consumed_with_vin(): void
    {
        $this->administrator();
        $materials = Material::factory()->count(3)->create(['unit' => 'gr']);
        foreach ($materials as $material) {
            $this->fundMaterial($material, '10');
        }
        $recipe = $this->formulaPayload($materials->map(fn (Material $material): array => [
            'material_id' => $material->id, 'grams' => '1.00',
        ])->all());
        $id = $this->postJson('/api/paint-formulas', $recipe)->assertCreated()->json('data.id');
        $this->postJson('/api/paint-formulas/'.$id.'/preview', ['quantity_grams' => '1.00'])
            ->assertOk()->assertJsonPath('total_ingredient_grams', '1.00')
            ->assertJsonPath('components.0.grams', '0.34')
            ->assertJsonPath('components.1.grams', '0.33')
            ->assertJsonPath('components.2.grams', '0.33');

        $this->postJson('/api/paint-formulas/'.$id.'/prepare', [
            'quantity_grams' => '1.00', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();
        $product = PaintFormula::findOrFail($id)->product;
        $this->assertSame('1.000000', $product->fresh()->inventoryBalance->quantity);
        $order = WorkOrder::factory()->create();
        $this->postJson('/api/work-orders/'.$order->id.'/material-consumptions', [
            'material_id' => $product->id, 'quantity' => '0.25',
            'unit_original' => 'gr', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.unit.vin', $order->diagnosis->unit->vin)
            ->assertJsonPath('data.material.code', $product->code);
        $this->assertSame('0.750000', $product->fresh()->inventoryBalance->quantity);
    }

    public function test_read_only_user_cannot_create_or_prepare_recipes(): void
    {
        $this->withPermissions(Permission::MaterialsView);
        $this->getJson('/api/paint-formulas')->assertOk();
        $this->getJson('/api/paint-preparations')->assertOk();
        $this->postJson('/api/paint-formulas', $this->formulaPayload([]))->assertForbidden();
    }
}
