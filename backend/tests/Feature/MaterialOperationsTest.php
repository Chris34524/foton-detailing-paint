<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\Process;
use App\Models\WorkOrder;
use App\Models\WorkOrderProcessStep;
use App\Services\ProcessFlowService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class MaterialOperationsTest extends OperationsTestCase
{
    public function test_material_catalog_normalizes_and_supports_lifecycle_without_delete(): void
    {
        $actor = $this->administrator();
        $id = $this->postJson('/api/materials', ['name' => ' Pintura ', 'unit' => ' l ', 'warehouse_reference' => ' WH-1 '])->assertCreated()
            ->assertJsonPath('data.code', 'PROD-001')->assertJsonPath('data.unit', 'L')->assertJsonPath('data.created_by.id', $actor->id)->json('data.id');
        $this->postJson('/api/materials', ['code' => 'PROD-999', 'name' => 'Otro', 'unit' => 'kg'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson('/api/materials/'.$id, ['code' => 'PROD-999'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson('/api/materials/'.$id, ['is_active' => false])->assertOk();
        $this->patchJson('/api/materials/'.$id, ['is_active' => true, 'name' => 'Nuevo'])->assertOk();
        $this->getJson('/api/materials/'.$id)->assertOk()->assertJsonPath('data.name', 'Nuevo');
        $this->getJson('/api/materials?search=PROD-001&unit=L&active=1&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/materials?sort=consumed_at')->assertUnprocessable();
        $this->deleteJson('/api/materials/'.$id)->assertMethodNotAllowed();
    }

    public function test_consumption_has_server_actor_vin_process_and_stable_material_snapshot(): void
    {
        $actor = $this->administrator();
        $material = Material::factory()->create(['unit' => 'kg', 'warehouse_reference' => 'W-1']);
        $this->fundMaterial($material, '5');
        $process = Process::factory()->create();
        app(ProcessFlowService::class)->replace([$process->id]);
        $order = WorkOrder::factory()->create();
        app(ProcessFlowService::class)->initialize($order);
        $id = $this->postJson('/api/work-orders/'.$order->id.'/material-consumptions', [
            'idempotency_key' => (string) Str::uuid(), 'material_id' => $material->id, 'quantity' => '1.750', 'work_order_process_step_id' => $order->fresh()->current_process_step_id, 'notes' => '  Aplicado ', 'external_reference' => 'EXT-1'])->assertCreated()
            ->assertJsonPath('data.quantity', '1.750000')->assertJsonPath('data.consumed_by.id', $actor->id)
            ->assertJsonPath('data.unit.vin', $order->diagnosis->unit->vin)->assertJsonPath('data.process_step.process_id', $process->id)->json('data.id');
        $material->update(['name' => 'Otro', 'is_active' => false]);
        $this->getJson('/api/material-consumptions?vin='.$order->diagnosis->unit->vin)->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.material.unit', 'Kg');
        $this->getJson('/api/work-orders/'.$order->id.'/material-consumptions')->assertOk()->assertJsonPath('data.0.notes', 'Aplicado');
        $this->getJson('/api/material-consumptions?search='.$order->diagnosis->unit->vin)->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertNotNull(MaterialConsumption::findOrFail($id)->consumed_at);
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-1], ['0.000'], ['1.2345678'], ['1000000000000'], ['NaN'], [null]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantity_rejected(mixed $quantity): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create();
        $material = Material::factory()->create();
        $this->postJson('/api/work-orders/'.$order->id.'/material-consumptions', ['material_id' => $material->id, 'quantity' => $quantity, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_inactive_terminal_cross_parent_and_forged_metadata_are_rejected(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create();
        $material = Material::factory()->create(['is_active' => false]);
        $url = '/api/work-orders/'.$order->id.'/material-consumptions';
        $payload = ['material_id' => $material->id, 'quantity' => 1, 'idempotency_key' => (string) Str::uuid()];
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('material_id');
        $material->update(['is_active' => true]);
        $this->fundMaterial($material, '5');
        $other = WorkOrderProcessStep::factory()->create();
        $this->postJson($url, $payload + ['work_order_process_step_id' => $other->id])->assertUnprocessable();
        foreach (['work_order_id', 'unit_id', 'vin', 'consumed_by', 'consumed_at', 'process_code'] as $field) {
            $this->postJson($url, $payload + [$field => null])->assertUnprocessable();
        }
        foreach (['completed', 'cancelled'] as $status) {
            $order->status = $status;
            $order->save();
            $this->postJson($url, $payload)->assertUnprocessable();
        }
        $order->status = 'queued';
        $order->save();
        $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data.process_step', null);
    }

    public function test_warehouse_contract_is_versioned_paginated_incremental_and_manage_only(): void
    {
        $this->administrator();
        Material::factory()->create(['updated_at' => now()->subDays(3)]);
        $new = Material::factory()->create();
        MaterialConsumption::factory()->for($new, 'material')->create();
        $since = urlencode(now()->subDay()->toISOString());
        $this->getJson('/api/integrations/warehouse/v1/materials?updated_since='.$since.'&per_page=1')->assertOk()
            ->assertJsonPath('contract_version', '1')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $new->id);
        $this->getJson('/api/integrations/warehouse/v1/material-consumptions?updated_since='.$since)->assertOk()->assertJsonPath('contract_version', '1')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/integrations/warehouse/v1/materials?updated_since=bad')->assertUnprocessable();
        $this->withPermissions(Permission::MaterialsView);
        $this->getJson('/api/integrations/warehouse/v1/materials')->assertForbidden();
        $this->getJson('/api/integrations/warehouse/v1/material-consumptions')->assertForbidden();
    }
}
