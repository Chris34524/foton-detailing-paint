<?php

namespace Tests\Feature;

use App\Enums\InventoryStockStatus;
use App\Enums\Permission;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class InventoryQueriesTest extends OperationsTestCase
{
    public static function states(): array
    {
        return [['0', null, null, 'no_parameters'], ['1', '5', null, 'no_parameters'], ['4.999999', '5', '10', 'critical'],
            ['5', '5', '10', 'reorder'], ['6', '5', '10', 'reorder'], ['6.000001', '5', '10', 'ok'], ['10', '5', '10', 'ok'], ['10.000001', '5', '10', 'overstock'],
            ['5.6', '5', '5.5', 'overstock'], ['0', '0', '0', 'reorder'], ['1', '0', '0', 'overstock'],
            ['0.000006', '0.000005', '0.00001', 'reorder'], ['0.000007', '0.000005', '0.00001', 'ok']];
    }

    #[DataProvider('states')]
    public function test_state_boundaries_and_reorder_precedence(string $quantity, ?string $minimum, ?string $maximum, string $expected): void
    {
        $this->assertSame($expected, InventoryStockStatus::calculate($quantity, $minimum, $maximum)->value);
    }

    public function test_min_max_validation_and_unit_lock_preserve_history(): void
    {
        $this->administrator();
        $payload = ['name' => 'Material', 'unit' => ' kg ', 'minimum_stock' => '5', 'maximum_stock' => '10', 'location' => ' Laboratorio de Pintura ', 'supplier' => ' Proveedor ', 'notes' => ' Observaciones '];
        $id = $this->postJson('/api/materials', $payload)->assertCreated()->assertJsonPath('data.unit', 'Kg')
            ->assertJsonPath('data.location', 'Laboratorio de Pintura')->assertJsonPath('data.stock_actual', '0.000000')->json('data.id');
        $this->patchJson('/api/materials/'.$id, ['maximum_stock' => '4'])->assertUnprocessable()->assertJsonValidationErrors('maximum_stock');
        $this->patchJson('/api/materials/'.$id, ['minimum_stock' => '-1'])->assertUnprocessable();
        $this->patchJson('/api/materials/'.$id, ['maximum_stock' => null, 'minimum_stock' => null])->assertOk()->assertJsonPath('data.stock_status', 'no_parameters');
        foreach (['stock_actual', 'initial_stock', 'entries', 'quantity', 'area'] as $field) {
            $this->patchJson('/api/materials/'.$id, [$field => '5'])->assertUnprocessable();
        }
        $this->postJson('/api/materials', array_replace($payload, ['unit' => 'toneladas']))->assertUnprocessable();
        $material = Material::findOrFail($id);
        $this->fundMaterial($material, '5');
        $this->patchJson('/api/materials/'.$id, ['unit' => 'gr'])->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->getJson('/api/materials/'.$id)->assertOk()->assertJsonPath('data.has_inventory_history', true);
    }

    public function test_dashboard_counts_alerts_actions_filters_and_pagination(): void
    {
        $this->administrator();
        foreach ([['C', '4'], ['R', '6'], ['O', '8'], ['S', '11']] as [$code,$quantity]) {
            $material = Material::factory()->create(['code' => $code, 'minimum_stock' => '5', 'maximum_stock' => '10']);
            $this->fundMaterial($material, $quantity);
        }
        Material::factory()->create(['code' => 'N']);
        $response = $this->getJson('/api/materials/inventory/dashboard?per_page=2')->assertOk()->assertJsonPath('indicators.total_components', 5)
            ->assertJsonPath('indicators.critical', 1)->assertJsonPath('indicators.reorder', 1)->assertJsonPath('indicators.ok', 1)
            ->assertJsonPath('indicators.overstock', 1)->assertJsonPath('indicators.no_parameters', 1)->assertJsonPath('alerts.meta.total', 4)
            ->assertJsonCount(2, 'alerts.data')->assertJsonPath('alerts.data.0.recommended_action', 'COMPRAR / REABASTECER');
        $this->getJson('/api/materials?stock_status=reorder')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'R');
        $this->getJson('/api/materials?stock_status=overstock')->assertOk()->assertJsonPath('data.0.stock_percentage', '110.00');
        $this->getJson('/api/materials?stock_status=no_parameters')->assertOk()->assertJsonPath('data.0.stock_percentage', null);
        $this->getJson('/api/materials?stock_status=unknown')->assertUnprocessable();
    }

    public function test_metadata_is_authoritative_and_permissions_are_reflected(): void
    {
        $this->withPermissions(Permission::MaterialsView, Permission::MaterialsConsume);
        $response = $this->getJson('/api/materials/meta')->assertOk()->assertJsonCount(8, 'units')->assertJsonCount(5, 'movement_types')
            ->assertJsonPath('areas', ['Pintura 1', 'Pintura 2', 'Lavado'])->assertJsonCount(1, 'allowed_movement_types')
            ->assertJsonPath('allowed_movement_types.0.value', 'exit');
        $this->getJson('/api/materials/inventory/dashboard')->assertOk();
    }

    public function test_movement_list_has_history_filters_safe_actors_and_pagination(): void
    {
        $this->administrator();
        $material = Material::factory()->create(['code' => 'FILTER']);
        $this->fundMaterial($material, '5');
        $entry = $this->postJson('/api/inventory-movements', ['material_id' => $material->id, 'movement_type' => 'entry', 'quantity_original' => '1', 'unit_original' => $material->unit,
            'area_destination' => 'Lavado', 'notes' => 'Recibido', 'idempotency_key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $this->getJson('/api/inventory-movements?material_id='.$material->id.'&movement_type=entry&area_destination=Lavado&date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $entry)->assertJsonMissingPath('data.0.performed_by.email')->assertJsonMissingPath('data.0.request_fingerprint');
        $this->getJson('/api/materials/'.$material->id.'/movements?per_page=1&page=2')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/inventory-movements?search=FILTER')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/inventory-movements/'.$entry)->assertOk()->assertJsonPath('data.notes', 'Recibido');
        foreach (['sort=quantity', 'movement_type=bad', 'area_destination=bad', 'date_from=bad', 'date_from=2026-11-03&date_to=2026-11-02', 'per_page=101'] as $filter) {
            $this->getJson('/api/inventory-movements?'.$filter)->assertUnprocessable();
        }
    }

    public function test_preview_is_read_only_and_does_not_allow_insufficient_stock(): void
    {
        $this->administrator();
        $material = Material::factory()->create(['unit' => 'L']);
        $this->fundMaterial($material, '5');
        $this->getJson('/api/materials/'.$material->id.'/quantity-preview?quantity_original=750&unit_original=ml&movement_type=exit')->assertOk()
            ->assertJsonPath('quantity_equivalent', '0.750000')->assertJsonPath('stock_after', '4.250000')->assertJsonPath('can_confirm', true);
        $this->getJson('/api/materials/'.$material->id.'/quantity-preview?quantity_original=6&unit_original=L')->assertOk()->assertJsonPath('can_confirm', false)->assertJsonPath('stock_after', null);
        $this->getJson('/api/materials/'.$material->id.'/quantity-preview?quantity_original=1&unit_original=gr')->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('5.000000', $material->inventoryBalance->quantity);
    }

    public function test_reconciliation_rebuilds_cache_without_inventing_movements(): void
    {
        $this->administrator();
        $material = Material::factory()->create();
        $this->fundMaterial($material, '5');
        DB::table('inventory_balances')->where('material_id', $material->id)->update(['quantity' => '99']);
        $this->artisan('inventory:reconcile', ['--material' => $material->id])->assertExitCode(1);
        $this->artisan('inventory:reconcile', ['--material' => $material->id, '--repair' => true])->assertSuccessful();
        $this->assertSame('5.000000', $material->inventoryBalance->quantity);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->getJson('/api/materials/'.$material->id.'/stock')->assertOk()->assertJsonPath('reconciliation.reconciled', true);
    }

    public function test_stock_and_history_endpoints_require_auth_and_view_permission(): void
    {
        $material = Material::factory()->create();
        foreach (['/api/materials/meta', '/api/materials/inventory/dashboard', '/api/materials/'.$material->id.'/stock', '/api/materials/'.$material->id.'/movements', '/api/inventory-movements'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->withPermissions();
        foreach (['/api/materials/meta', '/api/materials/inventory/dashboard', '/api/materials/'.$material->id.'/stock', '/api/inventory-movements'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_reconciliation_rejects_invalid_or_unknown_material_id(): void
    {
        foreach (['0', '-1', 'invalid', '999999'] as $id) {
            $this->artisan('inventory:reconcile', ['--material' => $id])->assertExitCode(2);
        }
    }
}
