<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Defect;
use App\Models\Process;
use App\Models\ProcessSequenceStep;
use App\Models\WorkOrder;
use App\Services\ProcessFlowService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ProcessOperationsTest extends OperationsTestCase
{
    public function test_catalog_normalizes_codes_validates_duplicates_and_preserves_creator(): void
    {
        $actor = $this->administrator();
        $id = $this->postJson('/api/processes', ['code' => ' detail ', 'name' => ' Detallado '])->assertCreated()
            ->assertJsonPath('data.code', 'DETAIL')->assertJsonPath('data.name', 'Detallado')->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.created_by.id', $actor->id)->json('data.id');
        $this->postJson('/api/processes', ['code' => 'detail', 'name' => 'Otro'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson('/api/processes/'.$id, ['name' => 'Nuevo', 'description' => ' Descripción ', 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson('/api/processes/'.$id, ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson('/api/processes/'.$id)->assertOk()->assertJsonPath('data.description', 'Descripción');
        $this->patchJson('/api/processes/'.$id, [])->assertUnprocessable();
        $this->postJson('/api/processes', ['code' => 'X', 'name' => '<b>HTML</b>'])->assertUnprocessable();
        $this->postJson('/api/processes', ['code' => 'X', 'name' => 'x', 'created_by' => null])->assertUnprocessable();
        $this->deleteJson('/api/processes/'.$id)->assertMethodNotAllowed();
        $this->assertSame('MODEL', Process::factory()->create(['code' => ' model '])->code);
    }

    public function test_catalog_pagination_filters_search_safe_sort_and_visibility(): void
    {
        $this->withPermissions(Permission::ProcessesView);
        Process::factory()->create(['code' => 'ALPHA', 'is_active' => true]);
        Process::factory()->create(['code' => 'BETA', 'is_active' => false]);
        $this->getJson('/api/processes?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/processes?active=0&search=beta')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'BETA');
        foreach (['sort=notes', 'per_page=101', 'direction=wrong', 'active=no'] as $query) {
            $this->getJson('/api/processes?'.$query)->assertUnprocessable();
        }
        $this->postJson('/api/processes', ['code' => 'NO', 'name' => 'No'])->assertForbidden();
    }

    public function test_sequence_reorder_is_atomic_and_rejects_invalid_or_inactive_ids(): void
    {
        $this->administrator();
        $a = Process::factory()->create();
        $b = Process::factory()->create();
        $inactive = Process::factory()->create(['is_active' => false]);
        $this->putJson('/api/process-sequence', ['process_ids' => [$a->id, $b->id]])->assertOk()->assertJsonPath('data.0.process.id', $a->id);
        $this->putJson('/api/process-sequence', ['process_ids' => [$b->id, $a->id]])->assertOk()->assertJsonPath('data.0.process.id', $b->id)->assertJsonPath('data.1.position', 2);
        foreach ([[$a->id, $a->id], [$inactive->id], [999999]] as $ids) {
            $this->putJson('/api/process-sequence', ['process_ids' => $ids])->assertUnprocessable();
            $this->assertSame([$b->id, $a->id], ProcessSequenceStep::orderBy('position')->pluck('process_id')->all());
        }
        $this->patchJson('/api/processes/'.$a->id, ['is_active' => false])->assertUnprocessable();
        $this->putJson('/api/process-sequence', ['process_ids' => []])->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/processes/'.$a->id, ['is_active' => false])->assertOk();
    }

    public function test_new_orders_snapshot_global_flow_and_catalog_edits_never_change_history(): void
    {
        $this->administrator();
        $a = Process::factory()->create(['code' => 'FIRST', 'name' => 'Primero']);
        $b = Process::factory()->create(['code' => 'SECOND']);
        app(ProcessFlowService::class)->replace([$a->id, $b->id]);
        $defect = Defect::factory()->create();
        $id = $this->postJson('/api/diagnoses/'.$defect->diagnosis_id.'/work-order')->assertCreated()->json('data.id');
        $this->getJson('/api/work-orders/'.$id.'/process-flow')->assertOk()->assertJsonPath('initialized', true)
            ->assertJsonPath('current.code', 'FIRST')->assertJsonPath('next.code', 'SECOND')->assertJsonCount(2, 'steps');
        $a->update(['code' => 'CHANGED', 'name' => 'Cambiado']);
        app(ProcessFlowService::class)->replace([$b->id]);
        $this->getJson('/api/work-orders/'.$id.'/process-flow')->assertOk()->assertJsonPath('current.code', 'FIRST')->assertJsonPath('current.name', 'Primero')->assertJsonPath('next.code', 'SECOND');
    }

    public function test_existing_orders_initialize_once_and_current_next_use_snapshot(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create();
        $this->getJson('/api/work-orders/'.$order->id.'/process-flow')->assertOk()->assertExactJson(['initialized' => false, 'current' => null, 'next' => null, 'steps' => []]);
        $this->postJson('/api/work-orders/'.$order->id.'/process-flow/initialize')->assertUnprocessable();
        $a = Process::factory()->create();
        $b = Process::factory()->create();
        app(ProcessFlowService::class)->replace([$a->id, $b->id]);
        $this->postJson('/api/work-orders/'.$order->id.'/process-flow/initialize')->assertCreated();
        $this->postJson('/api/work-orders/'.$order->id.'/process-flow/initialize')->assertUnprocessable();
        $last = $order->processSteps()->reorder('position', 'desc')->first();
        $order->current_process_step_id = $last->id;
        $order->save();
        $this->getJson('/api/work-orders/'.$order->id.'/process-flow')->assertOk()->assertJsonPath('next', null)->assertJsonPath('current.step_id', $last->id);
        $this->assertDatabaseCount('work_order_process_steps', 2);
    }

    public function test_no_sequence_keeps_phase_five_generation_compatible_and_read_has_no_side_effects(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $id = $this->postJson('/api/diagnoses/'.$defect->diagnosis_id.'/work-order')->assertCreated()->json('data.id');
        $this->getJson('/api/work-orders/'.$id.'/process-flow')->assertOk()->assertJsonPath('initialized', false);
        $this->assertDatabaseCount('work_order_process_steps', 0);
        $this->assertNull(WorkOrder::findOrFail($id)->current_process_step_id);
    }

    public function test_snapshot_and_catalog_foreign_keys_preserve_history(): void
    {
        $this->administrator();
        $process = Process::factory()->create();
        app(ProcessFlowService::class)->replace([$process->id]);
        $order = WorkOrder::factory()->create();
        app(ProcessFlowService::class)->initialize($order);
        $this->expectException(QueryException::class);
        $process->delete();
    }

    public function test_no_time_tracking_or_generic_traceability_routes_or_tables_exist(): void
    {
        foreach (['time_entries', 'activity_logs', 'work_sessions', 'traceability_events'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        foreach (Route::getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('~(timer|time-tracking|activities|pause|resume)~', $route->uri());
        }
    }
}
