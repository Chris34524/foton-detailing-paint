<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Defect;
use App\Models\DefectEvidence;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WorkOrderQueriesTest extends DiagnosisTestCase
{
    public function test_list_queue_meta_and_detail_require_authentication_and_permission(): void
    {
        $order = WorkOrder::factory()->create();
        $urls = ['/api/work-orders', '/api/work-orders/queue', '/api/work-orders/meta', '/api/work-orders/'.$order->id];
        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->withPermissions();
        foreach ($urls as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->withPermissions(Permission::WorkOrdersView);
        foreach ($urls as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/work-orders/999999')->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_operator_lookup_is_permission_protected_bounded_and_safe(): void
    {
        $this->getJson('/api/work-orders/operators')->assertUnauthorized();
        $this->withPermissions(Permission::WorkOrdersView);
        $this->getJson('/api/work-orders/operators')->assertForbidden();
        $operators = User::factory()->count(3)->sequence(['name' => 'A Operador'], ['name' => 'B Operador'], ['name' => 'C Operador'])->create()->each->assignRole(Role::Operator);
        User::factory()->create(['name' => 'No asignable'])->assignRole(Role::Supervisor);
        $this->withPermissions(Permission::WorkOrdersAssign);
        $response = $this->getJson('/api/work-orders/operators?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->assertSame(['id', 'name'], array_keys($response->json('data.0')));
        $this->getJson('/api/work-orders/operators?search=B%20Operador')->assertOk()->assertJsonPath('data.0.id', $operators[1]->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/work-orders/operators?per_page=101')->assertUnprocessable();
    }

    public function test_search_covers_unit_and_both_note_fields(): void
    {
        $this->withPermissions(Permission::WorkOrdersView);
        $order = WorkOrder::factory()->create(['notes' => 'Pintar cabina especial']);
        $order->diagnosis->update(['notes' => 'Diagnóstico lateral']);
        $order->diagnosis->unit->update(['model' => 'Camión-X99']);
        WorkOrder::factory()->create();
        foreach ([$order->diagnosis->unit->vin, 'X99', 'cabina especial', 'lateral'] as $term) {
            $this->getJson('/api/work-orders?search='.urlencode($term))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $order->id);
        }
    }

    public function test_list_has_server_pagination_filters_and_deterministic_sorting(): void
    {
        $this->withPermissions(Permission::WorkOrdersView);
        $operator = User::factory()->create();
        $a = WorkOrder::factory()->create(['priority' => 'low', 'created_at' => now()->subDay()]);
        $b = WorkOrder::factory()->create(['priority' => 'medium', 'status' => 'blocked', 'assigned_to' => $operator->id]);
        $c = WorkOrder::factory()->create(['priority' => 'critical', 'status' => 'completed']);
        $this->getJson('/api/work-orders?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', $c->id);
        $this->getJson('/api/work-orders?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $b->id);
        foreach (['status=blocked', 'priority=medium', 'assignment=assigned', 'assigned_to='.$operator->id] as $filter) {
            $this->getJson('/api/work-orders?'.$filter)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b->id);
        }
        $this->getJson('/api/work-orders?assignment=unassigned')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/work-orders?sort=priority&direction=asc')->assertOk()->assertJsonPath('data.0.id', $a->id)->assertJsonPath('data.1.id', $b->id)->assertJsonPath('data.2.id', $c->id);
        $this->getJson('/api/work-orders?sort=priority&direction=desc')->assertOk()->assertJsonPath('data.0.id', $c->id);
        $this->getJson('/api/work-orders?sort=updated_at&direction=asc')->assertOk();
        $this->getJson('/api/work-orders?sort=status')->assertOk();
        $this->getJson('/api/work-orders')->assertOk()->assertJsonMissingPath('data.0.diagnosis.defects');
    }

    public function test_invalid_filters_and_sort_inputs_are_validation_errors(): void
    {
        $this->withPermissions(Permission::WorkOrdersView);
        foreach (['status=assigned', 'priority=urgent', 'assignment=other', 'assigned_to=0', 'per_page=101', 'page=0', 'sort=notes', 'sort=priority;DROP', 'direction=sideways', 'search='.str_repeat('a', 256)] as $filter) {
            $this->getJson('/api/work-orders?'.$filter)->assertUnprocessable()->assertJsonMissingPath('exception');
            $this->getJson('/api/work-orders/queue?'.$filter)->assertUnprocessable();
        }
    }

    public function test_queue_excludes_terminal_orders_uses_rank_then_fifo_and_never_writes(): void
    {
        $this->withPermissions(Permission::WorkOrdersView);
        WorkOrder::factory()->create(['status' => 'completed', 'priority' => 'critical']);
        WorkOrder::factory()->create(['status' => 'cancelled', 'priority' => 'critical']);
        $low = WorkOrder::factory()->create(['priority' => 'low', 'created_at' => now()->subDays(10)]);
        $medium = WorkOrder::factory()->create(['priority' => 'medium']);
        $high = WorkOrder::factory()->create(['priority' => 'high']);
        $criticalOld = WorkOrder::factory()->create(['priority' => 'critical', 'created_at' => now()->subDay()]);
        $criticalNew = WorkOrder::factory()->create(['priority' => 'critical', 'created_at' => now()]);
        $criticalTie = WorkOrder::factory()->create(['priority' => 'critical', 'created_at' => $criticalNew->created_at]);
        $before = WorkOrder::all()->map->getAttributes()->all();
        $response = $this->getJson('/api/work-orders/queue')->assertOk()->assertJsonPath('meta.total', 6);
        $this->assertSame([$criticalOld->id, $criticalNew->id, $criticalTie->id, $high->id, $medium->id, $low->id], array_column($response->json('data'), 'id'));
        $this->getJson('/api/work-orders/queue?per_page=2&page=2')->assertOk()->assertJsonPath('data.0.id', $criticalTie->id);
        $this->getJson('/api/work-orders/queue?priority=low&assignment=unassigned')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/work-orders/queue?status=completed')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame($before, WorkOrder::all()->map->getAttributes()->all());
    }

    public function test_meta_labels_and_priority_rank_are_authoritative(): void
    {
        $this->withPermissions(Permission::WorkOrdersView);
        $this->getJson('/api/work-orders/meta')->assertOk()->assertExactJson([
            'statuses' => [
                ['value' => 'queued', 'label' => 'En cola', 'is_terminal' => false], ['value' => 'in_progress', 'label' => 'En proceso', 'is_terminal' => false],
                ['value' => 'blocked', 'label' => 'Bloqueada', 'is_terminal' => false], ['value' => 'completed', 'label' => 'Completada', 'is_terminal' => true], ['value' => 'cancelled', 'label' => 'Cancelada', 'is_terminal' => true],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'Baja', 'rank' => 1], ['value' => 'medium', 'label' => 'Media', 'rank' => 2],
                ['value' => 'high', 'label' => 'Alta', 'rank' => 3], ['value' => 'critical', 'label' => 'Crítica', 'rank' => 4],
            ],
        ]);
    }

    public function test_operator_can_read_source_and_private_evidence_only_for_generated_work(): void
    {
        $disk = config('diagnosis.evidence_disk');
        Storage::fake($disk);
        $defect = Defect::factory()->create();
        $file = UploadedFile::fake()->image('prueba.jpg');
        $path = $file->store('test-evidence', $disk);
        $evidence = DefectEvidence::factory()->for($defect)->create(['disk' => $disk, 'path' => $path, 'mime_type' => 'image/jpeg']);
        $operator = User::factory()->create()->assignRole(Role::Operator);
        $this->actingAs($operator, 'web');
        $imageUrl = '/api/diagnoses/'.$defect->diagnosis_id.'/defects/'.$defect->id.'/evidences/'.$evidence->id;
        $this->getJson($imageUrl)->assertForbidden();
        $order = WorkOrder::factory()->create(['diagnosis_id' => $defect->diagnosis_id]);
        $response = $this->getJson('/api/work-orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.diagnosis.defects.0.id', $defect->id)
            ->assertJsonPath('data.diagnosis.defects.0.evidences.0.view_url', $imageUrl)
            ->assertJsonMissingPath('data.created_by.email')
            ->assertJsonMissingPath('data.diagnosis.defects.0.evidences.0.path')
            ->assertJsonMissingPath('data.diagnosis.defects.0.evidences.0.disk');
        $this->assertStringNotContainsString('remember_token', $response->getContent());
        $this->getJson($imageUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson('/api/diagnoses/'.$defect->diagnosis_id)->assertForbidden();
        $this->getJson('/api/diagnoses')->assertForbidden();
        $this->postJson('/api/diagnoses/'.$defect->diagnosis_id.'/defects', [])->assertForbidden();
        $other = Defect::factory()->create();
        $this->getJson('/api/diagnoses/'.$other->diagnosis_id.'/defects/'.$defect->id.'/evidences/'.$evidence->id)->assertNotFound();
        $this->getJson('/api/diagnoses/'.$defect->diagnosis_id.'/defects/'.$other->id.'/evidences/'.$evidence->id)->assertNotFound();
        Auth::forgetGuards();
        $this->withPermissions();
        $this->getJson($imageUrl)->assertForbidden();
    }
}
