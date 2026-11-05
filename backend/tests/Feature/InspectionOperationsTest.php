<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistItem;
use App\Models\Rework;
use App\Models\UnitRelease;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class InspectionOperationsTest extends OperationsTestCase
{
    public function test_checklist_configuration_lifecycle_order_and_nested_integrity(): void
    {
        $this->administrator();
        $id = $this->postJson('/api/inspection/checklists', ['name' => ' Salida '])->assertCreated()->assertJsonPath('data.name', 'Salida')->assertJsonPath('data.is_active', true)->json('data.id');
        $a = $this->postJson('/api/inspection/checklists/'.$id.'/items', ['code' => ' first ', 'label' => 'Primer criterio'])->assertCreated()->assertJsonPath('data.code', 'FIRST')->json('data.id');
        $b = $this->postJson('/api/inspection/checklists/'.$id.'/items', ['code' => 'SECOND', 'label' => 'Segundo', 'is_required' => false])->assertCreated()->json('data.id');
        $this->putJson('/api/inspection/checklists/'.$id.'/item-order', ['item_ids' => [$b, $a]])->assertOk()->assertJsonPath('data.items.0.id', $b);
        $this->putJson('/api/inspection/checklists/'.$id.'/item-order', ['item_ids' => [$a, $a]])->assertUnprocessable();
        $this->putJson('/api/inspection/checklists/'.$id.'/item-order', ['item_ids' => [$a]])->assertUnprocessable();
        $this->patchJson('/api/inspection/checklists/'.$id.'/items/'.$a, ['is_active' => false, 'description' => ' Historial '])->assertOk();
        $this->patchJson('/api/inspection/checklists/'.$id.'/items/'.$a, ['is_active' => true])->assertOk();
        $other = InspectionChecklistItem::factory()->create();
        $this->patchJson('/api/inspection/checklists/'.$id.'/items/'.$other->id, ['label' => 'Ajeno'])->assertNotFound();
        $this->postJson('/api/inspection/checklists/'.$id.'/items', ['code' => 'first', 'label' => 'Duplicado'])->assertUnprocessable();
        $this->patchJson('/api/inspection/checklists/'.$id, ['is_active' => false])->assertOk();
        $this->patchJson('/api/inspection/checklists/'.$id, ['is_active' => true])->assertOk();
        $this->getJson('/api/inspection/checklists?active=1&search=Salida')->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson('/api/inspection/checklists/'.$id)->assertMethodNotAllowed();
    }

    public static function nonCompletedStates(): array
    {
        return [['queued'], ['in_progress'], ['blocked'], ['cancelled']];
    }

    #[DataProvider('nonCompletedStates')]
    public function test_only_completed_work_is_inspectable(string $status): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create(['status' => $status]);
        $checklist = $this->checklist();
        $this->postJson('/api/work-orders/'.$order->id.'/inspections', ['inspection_checklist_id' => $checklist->id])->assertUnprocessable();
    }

    public function test_creation_requires_active_nonempty_checklist_and_only_one_draft(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create(['status' => 'completed']);
        $checklist = InspectionChecklist::factory()->create();
        $url = '/api/work-orders/'.$order->id.'/inspections';
        $this->postJson($url, ['inspection_checklist_id' => $checklist->id])->assertUnprocessable();
        $checklist = $this->checklist();
        $checklist->update(['is_active' => false]);
        $this->postJson($url, ['inspection_checklist_id' => $checklist->id])->assertUnprocessable();
        $checklist->update(['is_active' => true]);
        $this->postJson($url, ['inspection_checklist_id' => $checklist->id])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.result', null);
        $this->postJson($url, ['inspection_checklist_id' => $checklist->id])->assertUnprocessable()->assertJsonValidationErrors('inspection');
        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_database_guards_against_two_draft_writers(): void
    {
        $draft = $this->draft();
        $this->expectException(QueryException::class);
        Inspection::factory()->create(['work_order_id' => $draft->work_order_id]);
    }

    public function test_snapshot_survives_catalog_rename_reorder_and_deactivation(): void
    {
        $this->administrator();
        $checklist = $this->checklist();
        $draft = $this->draft(null, $checklist);
        $item = $checklist->items()->first();
        $label = $item->label;
        $name = $checklist->name;
        $item->update(['label' => 'Cambiado', 'is_active' => false, 'is_required' => false]);
        $checklist->update(['name' => 'Otro checklist', 'is_active' => false]);
        $this->getJson('/api/inspections/'.$draft->id)->assertOk()->assertJsonPath('data.checklist.name', $name)
            ->assertJsonPath('data.items.0.item_label_snapshot', $label)->assertJsonPath('data.items.0.is_required_snapshot', true);
    }

    public function test_required_answers_and_cause_are_enforced_before_automatic_rework(): void
    {
        $actor = $this->administrator();
        $draft = $this->draft();
        $url = '/api/inspections/'.$draft->id;
        $this->postJson($url.'/complete')->assertUnprocessable()->assertJsonValidationErrors('items');
        $item = $draft->items()->first();
        $this->patchJson($url, ['items' => [['id' => $item->id, 'result' => 'non_compliant', 'comment' => ' Falló ']]])->assertOk()->assertJsonPath('data.items.0.comment', 'Falló');
        $this->postJson($url.'/complete')->assertUnprocessable()->assertJsonValidationErrors('rework_cause');
        $this->postJson($url.'/complete', ['rework_cause' => ' Corregir superficie '])->assertOk()->assertJsonPath('data.result', 'failed')
            ->assertJsonPath('data.rework.cause', 'Corregir superficie')->assertJsonPath('data.rework.created_by.id', $actor->id)
            ->assertJsonPath('data.unit.status', 'rework');
        $this->postJson($url.'/complete', ['rework_cause' => 'Otra'])->assertUnprocessable();
        $this->patchJson($url, ['notes' => 'No'])->assertUnprocessable();
        $this->assertDatabaseCount('reworks', 1);
        $this->assertSame('completed', $draft->workOrder->fresh()->status->value);
        $this->assertDatabaseCount('work_orders', 1);
    }

    public function test_later_pass_resolves_open_rework_and_readies_unit_then_releases_once(): void
    {
        $actor = $this->administrator();
        $failed = $this->draft();
        $this->answer($failed, 'non_compliant');
        $this->postJson('/api/inspections/'.$failed->id.'/complete', ['rework_cause' => 'Corregir'])->assertOk();
        $pass = $this->draft($failed->workOrder);
        $this->answer($pass);
        $this->postJson('/api/inspections/'.$pass->id.'/complete')->assertOk()->assertJsonPath('data.result', 'passed')->assertJsonPath('data.unit.status', 'ready_for_release');
        $this->assertSame('resolved', Rework::firstOrFail()->status->value);
        $this->assertNotNull(Rework::firstOrFail()->resolved_at);
        $this->getJson('/api/inspections/'.$pass->id)->assertOk()->assertJsonPath('data.release_checks.no_open_rework', true)->assertJsonPath('data.release_checks.latest_completed_inspection', true);
        $this->postJson('/api/inspections/'.$pass->id.'/release', ['notes' => ' Entrega '])->assertCreated()->assertJsonPath('data.released_by.id', $actor->id)->assertJsonPath('data.notes', 'Entrega');
        $this->postJson('/api/inspections/'.$pass->id.'/release')->assertUnprocessable();
        $this->assertDatabaseCount('unit_releases', 1);
        $this->assertSame('released', $pass->workOrder->diagnosis->unit->fresh()->status->value);
        $this->assertNotNull(UnitRelease::firstOrFail()->released_at);
    }

    public function test_optional_non_compliance_does_not_override_explicit_required_criteria_rule(): void
    {
        $this->administrator();
        $draft = $this->draft();
        $draft->items()->where('is_required_snapshot', true)->update(['result' => 'compliant']);
        $draft->items()->where('is_required_snapshot', false)->update(['result' => 'non_compliant']);
        $this->postJson('/api/inspections/'.$draft->id.'/complete')->assertOk()->assertJsonPath('data.result', 'passed');
        $this->assertDatabaseCount('reworks', 0);
    }

    public function test_forged_overall_results_actors_and_cross_inspection_answers_are_rejected(): void
    {
        $this->administrator();
        $draft = $this->draft();
        $other = $this->draft();
        $url = '/api/inspections/'.$draft->id;
        foreach (['result', 'status', 'inspected_by', 'completed_at', 'work_order_id'] as $field) {
            $this->patchJson($url, [$field => null])->assertUnprocessable();
            $this->postJson($url.'/complete', [$field => null])->assertUnprocessable();
        }
        $item = $other->items()->first();
        $this->patchJson($url, ['items' => [['id' => $item->id, 'result' => 'compliant']]])->assertUnprocessable();
        $item = $draft->items()->first();
        $this->patchJson($url, ['items' => [['id' => $item->id, 'result' => 'passed']]])->assertUnprocessable();
        $this->patchJson($url, ['items' => [['id' => $item->id, 'comment' => str_repeat('x', 2001)]]])->assertUnprocessable();
        $this->patchJson($url, ['items' => [['id' => $item->id, 'result' => 'compliant', 'is_required_snapshot' => false]]])->assertUnprocessable();
        foreach (['unit_id', 'inspection_id', 'released_by', 'released_at'] as $field) {
            $this->postJson($url.'/release', [$field => null])->assertUnprocessable();
        }
    }

    public static function releaseFailures(): array
    {
        return [['draft'], ['failed'], ['old'], ['order'], ['rework']];
    }

    #[DataProvider('releaseFailures')]
    public function test_all_release_preconditions_are_enforced(string $failure): void
    {
        $this->administrator();
        $draft = $this->draft();
        $this->answer($draft);
        if ($failure !== 'draft') {
            $this->postJson('/api/inspections/'.$draft->id.'/complete')->assertOk();
        }
        if ($failure === 'failed') {
            $draft->refresh();
            $draft->result = 'failed';
            $draft->save();
        }
        if ($failure === 'old') {
            $later = $this->draft($draft->workOrder);
            $this->answer($later);
            $this->postJson('/api/inspections/'.$later->id.'/complete')->assertOk();
        }
        if ($failure === 'order') {
            $order = $draft->workOrder;
            $order->status = 'cancelled';
            $order->save();
        }
        if ($failure === 'rework') {
            Rework::factory()->create(['work_order_id' => $draft->work_order_id]);
        }
        $this->postJson('/api/inspections/'.$draft->id.'/release')->assertUnprocessable()->assertJsonValidationErrors('release');
        $this->assertDatabaseCount('unit_releases', 0);
    }

    public function test_inspector_can_select_completed_orders_without_work_order_management_permission(): void
    {
        $operator = User::factory()->create()->assignRole(Role::Inspector);
        $this->actingAs($operator, 'web');
        $order = WorkOrder::factory()->create(['status' => 'completed']);
        WorkOrder::factory()->create(['status' => 'queued']);
        $checklist = $this->checklist();
        $this->getJson('/api/inspection/work-orders?search='.$order->diagnosis->unit->vin)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $order->id);
        $this->getJson('/api/work-orders')->assertForbidden();
        $this->postJson('/api/work-orders/'.$order->id.'/inspections', ['inspection_checklist_id' => $checklist->id])->assertCreated();
    }

    public function test_list_search_pagination_nested_history_and_resource_safety(): void
    {
        $this->administrator();
        $draft = $this->draft();
        $this->draft();
        $this->getJson('/api/inspections?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonMissingPath('data.0.items');
        $this->getJson('/api/work-orders/'.$draft->work_order_id.'/inspections')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/inspections?search='.$draft->workOrder->diagnosis->unit->vin)->assertOk()->assertJsonPath('data.0.id', $draft->id);
        $this->getJson('/api/inspections/'.$draft->id)->assertOk()->assertJsonMissingPath('data.inspected_by.email')->assertJsonMissingPath('data.draft_work_order_id');
        $this->getJson('/api/inspections?sort=code')->assertUnprocessable();
        $this->deleteJson('/api/inspections/'.$draft->id)->assertMethodNotAllowed();
    }

    public function test_database_duplicate_draft_race_returns_validation_and_rolls_back_snapshots(): void
    {
        $this->administrator();
        $order = WorkOrder::factory()->create(['status' => 'completed']);
        $checklist = $this->checklist();
        Event::listen('eloquent.creating: '.Inspection::class, function (Inspection $inspection): void {
            DB::table('inspections')->insert($inspection->getAttributes());
        });
        $this->postJson('/api/work-orders/'.$order->id.'/inspections', ['inspection_checklist_id' => $checklist->id])
            ->assertUnprocessable()->assertJsonValidationErrors('inspection')->assertJsonMissingPath('exception');
        $this->assertDatabaseCount('inspection_item_results', 0);
    }

    public function test_database_duplicate_release_race_returns_validation_and_preserves_unit_state(): void
    {
        $this->administrator();
        $inspection = $this->draft();
        $this->answer($inspection);
        $this->postJson('/api/inspections/'.$inspection->id.'/complete')->assertOk();
        Event::listen('eloquent.creating: '.UnitRelease::class, function (UnitRelease $release): void {
            DB::table('unit_releases')->insert($release->getAttributes());
        });
        $this->postJson('/api/inspections/'.$inspection->id.'/release')->assertUnprocessable()->assertJsonValidationErrors('release');
        $this->assertSame('ready_for_release', $inspection->workOrder->diagnosis->unit->fresh()->status->value);
        $this->assertDatabaseCount('unit_releases', 0);
    }
}
