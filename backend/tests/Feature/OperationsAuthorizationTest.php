<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Material;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class OperationsAuthorizationTest extends OperationsTestCase
{
    public static function readEndpoints(): array
    {
        return array_map(fn (string $url): array => [$url], [
            '/api/processes', '/api/process-sequence', '/api/materials', '/api/material-consumptions',
            '/api/inspection/checklists', '/api/inspections', '/api/inspection/work-orders',
            '/api/integrations/warehouse/v1/materials', '/api/integrations/warehouse/v1/material-consumptions']);
    }

    #[DataProvider('readEndpoints')]
    public function test_guest_and_unprivileged_users_cannot_read_domains(string $url): void
    {
        $this->getJson($url)->assertUnauthorized();
        $this->withPermissions();
        $this->getJson($url)->assertForbidden();
    }

    public function test_write_permissions_and_server_guards_are_independent(): void
    {
        $order = WorkOrder::factory()->create();
        $this->withPermissions(Permission::ProcessesView, Permission::MaterialsView, Permission::InspectionView);
        $this->postJson('/api/processes', [])->assertForbidden();
        $this->putJson('/api/process-sequence', ['process_ids' => []])->assertForbidden();
        $this->postJson('/api/work-orders/'.$order->id.'/process-flow/initialize')->assertForbidden();
        $this->postJson('/api/materials', [])->assertForbidden();
        $this->postJson('/api/work-orders/'.$order->id.'/material-consumptions', [])->assertForbidden();
        $this->postJson('/api/inspection/checklists', [])->assertForbidden();
        $this->postJson('/api/work-orders/'.$order->id.'/inspections', [])->assertForbidden();
        $draft = $this->draft();
        $this->patchJson('/api/inspections/'.$draft->id, ['notes' => 'x'])->assertForbidden();
        $this->postJson('/api/inspections/'.$draft->id.'/complete')->assertForbidden();
        $this->postJson('/api/inspections/'.$draft->id.'/release')->assertForbidden();
    }

    public function test_role_boundaries_and_super_admin_central_bypass_remain_unchanged(): void
    {
        $operator = User::factory()->create()->assignRole(Role::Operator);
        $this->actingAs($operator, 'web');
        $this->getJson('/api/materials')->assertOk();
        $order = WorkOrder::factory()->create();
        $material = Material::factory()->create();
        $this->fundMaterial($material, '5');
        $this->postJson('/api/work-orders/'.$order->id.'/material-consumptions', ['material_id' => $material->id, 'quantity' => 1, 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->postJson('/api/materials', ['code' => 'NO', 'name' => 'No', 'unit' => 'kg'])->assertForbidden();
        $this->getJson('/api/work-orders/'.$order->id.'/process-flow')->assertOk();
        Auth::forgetGuards();
        $super = User::factory()->create()->assignRole(Role::SuperAdmin);
        $this->actingAs($super, 'web');
        $this->postJson('/api/processes', ['code' => 'SUPER', 'name' => 'Permitido'])->assertCreated();
        $this->assertFalse($operator->can(Permission::ProcessesManage));
        $this->assertFalse($operator->can(Permission::InspectionManage));
    }
}
