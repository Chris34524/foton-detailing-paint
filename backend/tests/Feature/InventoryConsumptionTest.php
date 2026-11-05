<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\WorkOrder;
use App\Models\WorkOrderProcessStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class InventoryConsumptionTest extends OperationsTestCase
{
    public function test_consumption_creates_exactly_one_linked_exit_and_retry_does_not_deduct_again(): void
    {
        $actor = $this->administrator();
        $material = Material::factory()->create(['unit' => 'L']);
        $this->fundMaterial($material, '5');
        $step = WorkOrderProcessStep::factory()->create();
        $order = $step->workOrder;
        $payload = ['material_id' => $material->id, 'quantity' => '750', 'unit_original' => 'ml', 'area_destination' => 'Pintura 1', 'work_order_process_step_id' => $step->id, 'idempotency_key' => (string) Str::uuid()];
        $url = '/api/work-orders/'.$order->id.'/material-consumptions';
        $first = $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data.quantity', '0.750000')->assertJsonPath('data.material.unit', 'L')
            ->assertJsonPath('data.unit.vin', $order->diagnosis->unit->vin)->assertJsonPath('data.process_step.step_id', $step->id)
            ->assertJsonPath('data.inventory_movement.stock_after', '4.250000')->assertJsonPath('data.consumed_by.id', $actor->id);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('idempotent_replay', true);
        $this->assertDatabaseCount('material_consumptions', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('4.250000', $material->inventoryBalance->quantity);
        $consumption = MaterialConsumption::firstOrFail();
        $this->assertSame($consumption->inventory_movement_id, InventoryMovement::where('source_kind', 'consumption')->sole()->id);
        $order->status = 'completed';
        $order->save();
        $this->postJson($url, $payload)->assertOk();
    }

    public function test_failed_consumption_rolls_back_movement_balance_and_folio(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->fundMaterial($material, '5');
        $order = WorkOrder::factory()->create();
        $before = DB::table('inventory_sequences')->where('name', 'movement_folio_v2')->value('next_value');
        Event::listen('eloquent.creating: '.MaterialConsumption::class, function (): never {
            throw new \RuntimeException('Simulated consumption failure');
        });
        $this->postJson('/api/work-orders/'.$order->id.'/material-consumptions', ['material_id' => $material->id, 'quantity' => '1', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(500)->assertJsonMissingPath('exception');
        $this->assertDatabaseCount('material_consumptions', 0);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('5.000000', $material->inventoryBalance->quantity);
        $this->assertEquals($before, DB::table('inventory_sequences')->where('name', 'movement_folio_v2')->value('next_value'));
    }

    public function test_consumption_rejects_other_order_step_insufficient_stock_and_forged_fields(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->fundMaterial($material, '1');
        $order = WorkOrder::factory()->create();
        $other = WorkOrderProcessStep::factory()->create();
        $payload = ['material_id' => $material->id, 'quantity' => '1', 'idempotency_key' => (string) Str::uuid()];
        $url = '/api/work-orders/'.$order->id.'/material-consumptions';
        $this->postJson($url, $payload + ['work_order_process_step_id' => $other->id])->assertUnprocessable();
        $this->postJson($url, array_replace($payload, ['quantity' => '2']))->assertUnprocessable();
        $this->postJson($url, $payload + ['inventory_movement_id' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('material_consumptions', 0);
    }

    public function test_legacy_consumptions_remain_visible_without_fabricated_inventory(): void
    {
        $this->administrator();
        $legacy = MaterialConsumption::factory()->create();
        $this->getJson('/api/material-consumptions')->assertOk()->assertJsonPath('data.0.inventory_tracking', 'legacy_untracked')->assertJsonPath('data.0.inventory_movement', null);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertNull($legacy->inventory_movement_id);
        $this->getJson('/api/integrations/warehouse/v1/materials')->assertOk()->assertJsonPath('data.0.stock_actual', '0.000000');
        $this->getJson('/api/integrations/warehouse/v1/material-consumptions')->assertOk()->assertJsonPath('data.0.inventory_movement', null);
    }
}
