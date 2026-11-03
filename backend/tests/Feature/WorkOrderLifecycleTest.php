<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;

class WorkOrderLifecycleTest extends DiagnosisTestCase
{
    public static function transitions(): array
    {
        return [
            ['queued', 'in_progress'], ['queued', 'blocked'], ['queued', 'cancelled'],
            ['in_progress', 'blocked'], ['in_progress', 'completed'], ['in_progress', 'cancelled'],
            ['blocked', 'queued'], ['blocked', 'in_progress'], ['blocked', 'cancelled'],
        ];
    }

    #[DataProvider('transitions')]
    public function test_all_allowed_transitions(string $from, string $to): void
    {
        $this->withPermissions(Permission::WorkOrdersUpdate);
        $operator = User::factory()->create()->assignRole(Role::Operator);
        $order = WorkOrder::factory()->create(['status' => $from, 'assigned_to' => $operator->id]);
        $this->patchJson('/api/work-orders/'.$order->id.'/status', ['status' => $to])->assertOk()->assertJsonPath('data.status', $to);
    }

    public static function invalidTransitions(): array
    {
        return [['queued', 'completed'], ['completed', 'queued'], ['cancelled', 'in_progress'], ['blocked', 'completed'], ['queued', 'queued'], ['in_progress', 'queued']];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_are_rejected(string $from, string $to): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create(['status' => $from]);
        $this->patchJson('/api/work-orders/'.$order->id.'/status', ['status' => $to])->assertUnprocessable();
        $this->assertSame($from, $order->fresh()->status->value);
    }

    public function test_start_requires_assignment_and_transition_options_are_authoritative(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create();
        $this->getJson('/api/work-orders/'.$order->id)->assertOk()->assertJsonPath('data.allowed_status_transitions', [
            ['value' => 'blocked', 'label' => 'Bloqueada'], ['value' => 'cancelled', 'label' => 'Cancelada'],
        ]);
        $this->patchJson('/api/work-orders/'.$order->id.'/status', ['status' => 'in_progress'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson('/api/work-orders/'.$order->id.'/status', ['status' => 'assigned'])->assertUnprocessable();
        $this->patchJson('/api/work-orders/'.$order->id.'/status', [])->assertUnprocessable();
    }

    public function test_assign_reassign_and_unassign_record_only_server_metadata(): void
    {
        $actor = $this->withPermissions(Permission::WorkOrdersAssign);
        $operators = User::factory()->count(2)->create()->each->assignRole(Role::Operator);
        $order = WorkOrder::factory()->create();
        foreach ($operators as $operator) {
            $this->patchJson('/api/work-orders/'.$order->id.'/assignment', ['assigned_to' => $operator->id])->assertOk()
                ->assertJsonPath('data.assigned_to.id', $operator->id)->assertJsonPath('data.assigned_by.id', $actor->id)
                ->assertJsonPath('data.status', 'queued')->assertJsonMissingPath('data.assigned_to.email');
            $this->assertNotNull($order->fresh()->assigned_at);
            $this->assertSame($actor->id, $order->fresh()->assignedBy->id);
        }
        $this->patchJson('/api/work-orders/'.$order->id.'/assignment', ['assigned_to' => null])->assertOk()
            ->assertJsonPath('data.assigned_to', null)->assertJsonPath('data.assigned_at', null)->assertJsonPath('data.assigned_by.id', $actor->id);
    }

    public function test_arbitrary_users_and_forged_assignment_metadata_are_rejected(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create();
        $user = User::factory()->create()->assignRole(Role::Admin);
        foreach ([$user->id, 999999, -1, 'invalid'] as $id) {
            $this->patchJson('/api/work-orders/'.$order->id.'/assignment', ['assigned_to' => $id])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        }
        $this->patchJson('/api/work-orders/'.$order->id.'/assignment', [])->assertUnprocessable();
        foreach (['assigned_by', 'assigned_at', 'status', 'priority', 'diagnosis_id', 'created_by'] as $field) {
            $this->patchJson('/api/work-orders/'.$order->id.'/assignment', ['assigned_to' => null, $field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_in_progress_allows_reassignment_but_not_unassignment(): void
    {
        $this->administrator();
        $operators = User::factory()->count(2)->create()->each->assignRole(Role::Operator);
        $order = WorkOrder::factory()->create(['status' => 'in_progress', 'assigned_to' => $operators[0]->id]);
        $this->patchJson('/api/work-orders/'.$order->id.'/assignment', ['assigned_to' => null])->assertUnprocessable();
        $this->patchJson('/api/work-orders/'.$order->id.'/assignment', ['assigned_to' => $operators[1]->id])->assertOk();
    }

    public static function terminalStates(): array
    {
        return [['completed'], ['cancelled']];
    }

    #[DataProvider('terminalStates')]
    public function test_terminal_orders_reject_all_operational_modifications(string $state): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create(['status' => $state]);
        foreach (['' => ['priority' => 'critical'], '/assignment' => ['assigned_to' => null], '/status' => ['status' => 'queued']] as $suffix => $payload) {
            $this->patchJson('/api/work-orders/'.$order->id.$suffix, $payload)->assertUnprocessable();
        }
        $this->patchJson('/api/work-orders/'.$order->id, ['notes' => 'Cambio'])->assertUnprocessable();
        $this->getJson('/api/work-orders/'.$order->id)->assertOk()->assertJsonPath('data.is_terminal', true)->assertJsonPath('data.allowed_status_transitions', []);
    }

    #[DataProviderExternal(WorkOrderGenerationTest::class, 'priorityCases')]
    public function test_priority_updates_accept_all_levels(string $priority): void
    {
        $this->withPermissions(Permission::WorkOrdersUpdate);
        $order = WorkOrder::factory()->create();
        $this->patchJson('/api/work-orders/'.$order->id, ['priority' => $priority, 'notes' => '  Nota  '])->assertOk()
            ->assertJsonPath('data.priority', $priority)->assertJsonPath('data.notes', 'Nota');
    }

    public function test_general_update_is_limited_and_validated(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create();
        foreach ([[], ['priority' => 'urgent'], ['notes' => '<script>x</script>'], ['notes' => str_repeat('x', 5001)]] as $payload) {
            $this->patchJson('/api/work-orders/'.$order->id, $payload)->assertUnprocessable();
        }
        foreach (['diagnosis_id', 'status', 'assigned_to', 'assigned_by', 'assigned_at', 'created_by'] as $field) {
            $this->patchJson('/api/work-orders/'.$order->id, ['notes' => 'OK', $field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson('/api/work-orders/'.$order->id, ['notes' => '  '])->assertOk()->assertJsonPath('data.notes', null);
        $this->patchJson('/api/work-orders/'.$order->id.'/status', ['status' => 'blocked', 'assigned_to' => 1])->assertUnprocessable();
    }

    public function test_mutations_reload_state_instead_of_trusting_stale_models(): void
    {
        $order = WorkOrder::factory()->create();
        $stale = clone $order;
        $order->status = 'completed';
        $order->save();
        $this->expectException(ValidationException::class);
        app(WorkOrderService::class)->changeStatus($stale, WorkOrderStatus::Blocked);
    }

    public function test_permissions_remain_separate_and_operator_is_read_only(): void
    {
        $order = WorkOrder::factory()->create();
        $base = '/api/work-orders/'.$order->id;
        foreach ([$base => ['notes' => 'x'], $base.'/status' => ['status' => 'blocked'], $base.'/assignment' => ['assigned_to' => null]] as $url => $payload) {
            $this->patchJson($url, $payload)->assertUnauthorized();
        }
        $this->withPermissions(Permission::WorkOrdersView);
        $this->getJson($base)->assertOk();
        $this->patchJson($base, ['notes' => 'x'])->assertForbidden();
        $this->patchJson($base.'/status', ['status' => 'blocked'])->assertForbidden();
        $this->patchJson($base.'/assignment', ['assigned_to' => null])->assertForbidden();
        $this->withPermissions(Permission::WorkOrdersAssign);
        $this->patchJson($base, ['priority' => 'low'])->assertForbidden();
        $this->withPermissions(Permission::WorkOrdersUpdate);
        $this->patchJson($base.'/assignment', ['assigned_to' => null])->assertForbidden();
        $operator = User::factory()->create()->assignRole(Role::Operator);
        Auth::forgetGuards();
        $this->actingAs($operator, 'web');
        $this->getJson($base)->assertOk();
        $this->patchJson($base, ['notes' => 'x'])->assertForbidden();
        $this->patchJson($base.'/assignment', ['assigned_to' => null])->assertForbidden();
    }
}
