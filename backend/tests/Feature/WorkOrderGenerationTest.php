<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\Defect;
use App\Models\Diagnosis;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;

class WorkOrderGenerationTest extends DiagnosisTestCase
{
    public function test_guest_cannot_generate(): void
    {
        $source = Diagnosis::factory()->create();
        $this->postJson('/api/diagnoses/'.$source->id.'/work-order')->assertUnauthorized();
    }

    public function test_creation_requires_both_work_order_create_and_diagnosis_access(): void
    {
        $source = Diagnosis::factory()->create();
        foreach ([[], [Permission::WorkOrdersCreate], [Permission::DiagnosisView]] as $permissions) {
            $this->withPermissions(...$permissions);
            $this->postJson('/api/diagnoses/'.$source->id.'/work-order')->assertForbidden();
        }
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_empty_diagnosis_is_rejected(): void
    {
        $this->administrator();
        $source = Diagnosis::factory()->create();
        $this->postJson('/api/diagnoses/'.$source->id.'/work-order')->assertUnprocessable()->assertJsonValidationErrors('diagnosis');
    }

    public function test_generation_preserves_source_and_unit_and_records_server_owned_values(): void
    {
        $actor = $this->withPermissions(Permission::WorkOrdersCreate, Permission::DiagnosisView);
        $source = Diagnosis::factory()->create();
        $defects = Defect::factory()->count(3)->sequence(['priority' => 'low'], ['priority' => 'high'], ['priority' => 'medium'])->for($source)->create();
        $before = [$source->getAttributes(), $source->unit->getAttributes(), $defects->map->getAttributes()->all()];
        $response = $this->postJson('/api/diagnoses/'.$source->id.'/work-order', ['notes' => '  Reparar cabina  '])->assertCreated()
            ->assertJsonPath('data.status', 'queued')->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.notes', 'Reparar cabina')->assertJsonPath('data.created_by.id', $actor->id)
            ->assertJsonPath('data.assigned_to', null)->assertJsonPath('data.diagnosis.defect_count', 3);
        $order = WorkOrder::findOrFail($response->json('data.id'));
        $this->assertSame($source->id, $order->diagnosis_id);
        $this->assertEquals($before, [$source->fresh()->getAttributes(), $source->unit->fresh()->getAttributes(), $defects->map(fn (Defect $defect) => $defect->fresh()->getAttributes())->all()]);
        $this->assertSame(WorkOrderStatus::Queued, $order->status);
        $this->assertSame(WorkOrderPriority::High, $order->priority);
        $this->assertSame($order->id, $source->fresh()->workOrder->id);
        $this->assertSame($actor->id, $order->createdBy->id);
        $this->assertNull($order->assignedTo);
        $this->assertNull($order->assignedBy);
        $this->withPermissions(Permission::DiagnosisView);
        $this->getJson('/api/diagnoses/'.$source->id)->assertOk()->assertJsonPath('data.work_order.id', $order->id)
            ->assertJsonPath('data.work_order_priority_preview.value', 'high');
        $this->getJson('/api/diagnoses')->assertOk()->assertJsonMissingPath('data.0.work_order')->assertJsonMissingPath('data.0.work_order_priority_preview');
    }

    public static function priorityCases(): array
    {
        return array_map(fn (WorkOrderPriority $priority) => [$priority->value], WorkOrderPriority::cases());
    }

    #[DataProvider('priorityCases')]
    public function test_initial_priority_supports_every_level(string $priority): void
    {
        $this->administrator();
        $defect = Defect::factory()->create(['priority' => $priority]);
        $this->postJson('/api/diagnoses/'.$defect->diagnosis_id.'/work-order')->assertCreated()->assertJsonPath('data.priority', $priority);
    }

    public function test_duplicate_generation_is_a_clean_validation_error(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $url = '/api/diagnoses/'.$defect->diagnosis_id.'/work-order';
        $this->postJson($url)->assertCreated();
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('diagnosis')
            ->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
        $this->assertDatabaseCount('work_orders', 1);
    }

    public function test_database_uniqueness_is_the_final_duplicate_writer_gate(): void
    {
        $order = WorkOrder::factory()->create();
        $this->expectException(QueryException::class);
        WorkOrder::factory()->create(['diagnosis_id' => $order->diagnosis_id]);
    }

    public function test_source_priority_changes_do_not_rewrite_existing_work_order(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create(['priority' => 'low']);
        $id = $this->postJson('/api/diagnoses/'.$defect->diagnosis_id.'/work-order')->assertCreated()->json('data.id');
        $defect->update(['priority' => 'critical']);
        $this->assertSame(WorkOrderPriority::Low, WorkOrder::findOrFail($id)->priority);
    }

    public function test_generation_rejects_forged_metadata_and_invalid_notes(): void
    {
        $this->administrator();
        $source = Defect::factory()->create()->diagnosis;
        foreach (['diagnosis_id', 'unit_id', 'status', 'priority', 'created_by', 'assigned_to', 'assigned_by', 'assigned_at'] as $field) {
            $this->postJson('/api/diagnoses/'.$source->id.'/work-order', [$field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['<b>HTML</b>', str_repeat('x', 5001), ['invalid']] as $notes) {
            $this->postJson('/api/diagnoses/'.$source->id.'/work-order', ['notes' => $notes])->assertUnprocessable()->assertJsonValidationErrors('notes');
        }
        $this->postJson('/api/diagnoses/'.$source->id.'/work-order', ['notes' => '  '])->assertCreated()->assertJsonPath('data.notes', null);
    }

    public function test_creator_deletion_preserves_history(): void
    {
        $actor = User::factory()->create();
        $operator = User::factory()->create();
        $order = WorkOrder::factory()->create(['created_by' => $actor->id, 'assigned_by' => $actor->id, 'assigned_to' => $operator->id, 'assigned_at' => now()]);
        $actor->delete();
        $operator->delete();
        $this->assertNotNull($order->fresh());
        $this->assertNull($order->fresh()->created_by);
        $this->assertNull($order->fresh()->assigned_by);
        $this->assertNull($order->fresh()->assigned_to);
    }

    public function test_source_diagnosis_deletion_is_restricted(): void
    {
        $order = WorkOrder::factory()->create();
        $this->expectException(QueryException::class);
        $order->diagnosis->delete();
    }

    public function test_super_admin_uses_centralized_bypass_and_no_delete_route_exists(): void
    {
        $actor = User::factory()->create()->assignRole(Role::SuperAdmin);
        $this->actingAs($actor, 'web');
        $defect = Defect::factory()->create();
        $id = $this->postJson('/api/diagnoses/'.$defect->diagnosis_id.'/work-order')->assertCreated()->json('data.id');
        $this->getJson('/api/work-orders/'.$id)->assertOk();
        $this->deleteJson('/api/work-orders/'.$id)->assertMethodNotAllowed();
    }
}
