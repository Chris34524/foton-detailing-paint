<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\InventoryMovement;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryMovementTest extends OperationsTestCase
{
    private function payload(Material $material, string $type = 'entry', string $quantity = '1', array $extra = []): array
    {
        return array_replace(['material_id' => $material->id, 'movement_type' => $type, 'quantity_original' => $quantity, 'unit_original' => $material->unit,
            'area_destination' => 'Pintura 1', 'notes' => null, 'idempotency_key' => (string) Str::uuid()], $extra);
    }

    public function test_opening_entries_exits_and_adjustments_share_exact_ledger_and_folios(): void
    {
        $actor = $this->administrator();
        $material = Material::factory()->create(['unit' => 'L']);
        $types = [['opening_balance', '5', '5.000000'], ['entry', '2', '7.000000'], ['exit', '750', '6.250000'], ['adjustment_in', '0.5', '6.750000'], ['adjustment_out', '0.25', '6.500000']];
        foreach ($types as $index => [$type,$quantity,$stock]) {
            $extra = $type === 'exit' ? ['unit_original' => 'ml'] : [];
            if (str_starts_with($type, 'adjustment')) {
                $extra['notes'] = 'Corrección autorizada';
            }
            $this->postJson('/api/inventory-movements', $this->payload($material, $type, $quantity, $extra))->assertCreated()
                ->assertJsonPath('data.stock_after', $stock)->assertJsonPath('data.folio', 'MOV-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT))
                ->assertJsonPath('data.performed_by.id', $actor->id);
        }
        $this->getJson('/api/materials/'.$material->id.'/stock')->assertOk()->assertJsonPath('data.stock_actual', '6.500000')
            ->assertJsonPath('data.initial_stock', '5.000000')->assertJsonPath('data.entries', '2.000000')->assertJsonPath('data.exits', '0.750000')
            ->assertJsonPath('data.adjustments_in', '0.500000')->assertJsonPath('data.adjustments_out', '0.250000')->assertJsonPath('reconciliation.reconciled', true);
        $this->assertDatabaseCount('inventory_movements', 5);
    }

    public function test_opening_is_unique_and_must_precede_other_movements(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->postJson('/api/inventory-movements', $this->payload($material, 'opening_balance', '10'))->assertCreated();
        $this->postJson('/api/inventory-movements', $this->payload($material, 'opening_balance', '10'))->assertUnprocessable();
        $other = Material::factory()->create();
        $this->postJson('/api/inventory-movements', $this->payload($other))->assertCreated();
        $this->postJson('/api/inventory-movements', $this->payload($other, 'opening_balance'))->assertUnprocessable();
    }

    public function test_insufficient_stock_does_not_persist_movement_or_change_counter(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->fundMaterial($material, '1');
        $counter = DB::table('inventory_sequences')->where('name', 'movement_folio_v2')->value('next_value');
        $this->postJson('/api/inventory-movements', $this->payload($material, 'exit', '2'))->assertUnprocessable()->assertJsonValidationErrors('quantity_original');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('1.000000', $material->inventoryBalance->quantity);
        $this->assertEquals($counter, DB::table('inventory_sequences')->where('name', 'movement_folio_v2')->value('next_value'));
    }

    public function test_retries_return_same_movement_and_conflicting_keys_are_rejected(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $payload = $this->payload($material, 'entry', '5');
        $id = $this->postJson('/api/inventory-movements', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/inventory-movements', $payload)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('idempotent_replay', true);
        $this->postJson('/api/inventory-movements', array_replace($payload, ['quantity_original' => '6']))->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('5.000000', $material->inventoryBalance->quantity);
        $this->withPermissions(Permission::MaterialsManage);
        $this->postJson('/api/inventory-movements', $payload)->assertUnprocessable();
    }

    public function test_adjustments_require_reason_and_only_known_units_and_destinations_are_accepted(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->postJson('/api/inventory-movements', $this->payload($material, 'adjustment_in'))->assertUnprocessable()->assertJsonValidationErrors('notes');
        foreach ([['unit_original' => 'hojas'], ['area_destination' => 'Inventada'], ['quantity_original' => '1.0000001'], ['quantity_original' => -1], ['idempotency_key' => null], ['performed_by' => 1], ['folio' => 'MOV-9999'], ['stock_after' => '20']] as $extra) {
            $this->postJson('/api/inventory-movements', $this->payload($material, 'entry', '1', $extra))->assertUnprocessable();
        }
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_confirmed_history_is_immutable_and_corrections_are_new_movements(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $id = $this->postJson('/api/inventory-movements', $this->payload($material))->assertCreated()->json('data.id');
        $this->patchJson('/api/inventory-movements/'.$id, ['notes' => 'Cambio'])->assertMethodNotAllowed();
        $this->deleteJson('/api/inventory-movements/'.$id)->assertMethodNotAllowed();
        $movement = InventoryMovement::findOrFail($id);
        try {
            $movement->notes = 'Cambio';
            $movement->save();
            $this->fail('Expected immutability guard');
        } catch (\LogicException) {
        }
        $this->postJson('/api/inventory-movements', $this->payload($material, 'adjustment_out', '1', ['notes' => 'Compensación']))->assertCreated()->assertJsonPath('data.stock_after', '0.000000');
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_folios_continue_past_four_digits_without_reusing_history(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        DB::table('inventory_sequences')->where('name', 'movement_folio_v2')->update(['next_value' => 10000]);
        $this->postJson('/api/inventory-movements', $this->payload($material))->assertCreated()->assertJsonPath('data.folio', 'MOV-10000');
        $this->postJson('/api/inventory-movements', $this->payload($material))->assertCreated()->assertJsonPath('data.folio', 'MOV-10001');
    }

    public function test_rbac_limits_consume_to_outbound_and_manager_to_other_types(): void
    {
        $material = Material::factory()->create();
        $this->fundMaterial($material, '5');
        $this->postJson('/api/inventory-movements', $this->payload($material))->assertUnauthorized();
        $this->withPermissions(Permission::MaterialsView);
        $this->postJson('/api/inventory-movements', $this->payload($material, 'exit'))->assertForbidden();
        $this->withPermissions(Permission::MaterialsConsume);
        $this->postJson('/api/inventory-movements', $this->payload($material, 'exit'))->assertCreated();
        foreach (['entry', 'opening_balance', 'adjustment_in', 'adjustment_out'] as $type) {
            $this->postJson('/api/inventory-movements', $this->payload($material, $type))->assertForbidden();
        }
        $this->withPermissions(Permission::MaterialsManage);
        $this->postJson('/api/inventory-movements', $this->payload($material, 'entry'))->assertCreated();
    }

    public function test_fractional_arithmetic_never_accumulates_binary_rounding_error(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        foreach (['0.1', '0.2', '0.000001'] as $quantity) {
            $this->postJson('/api/inventory-movements', $this->payload($material, 'entry', $quantity))->assertCreated();
        }
        $this->postJson('/api/inventory-movements', $this->payload($material, 'exit', '0.3'))->assertCreated()->assertJsonPath('data.stock_after', '0.000001');
    }

    public function test_writes_fail_closed_when_balance_cache_needs_reconciliation(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->fundMaterial($material, '5');
        DB::table('inventory_balances')->where('material_id', $material->id)->update(['quantity' => '99']);
        $this->postJson('/api/inventory-movements', $this->payload($material))->assertUnprocessable()->assertJsonValidationErrors('inventory');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->artisan('inventory:reconcile', ['--material' => $material->id, '--repair' => true])->assertSuccessful();
        $this->postJson('/api/inventory-movements', $this->payload($material))->assertCreated()->assertJsonPath('data.stock_after', '6.000000');
    }
}
