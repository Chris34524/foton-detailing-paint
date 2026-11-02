<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\Defect;
use App\Models\Diagnosis;
use App\Models\Unit;
use App\Models\User;
use App\Policies\DiagnosisPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class DiagnosisApiTest extends DiagnosisTestCase
{
    public static function endpoints(): array
    {
        return [['GET', '/api/diagnoses'], ['GET', '/api/diagnoses/{d}'], ['PATCH', '/api/diagnoses/{d}'],
            ['GET', '/api/units/{u}/diagnoses'], ['POST', '/api/units/{u}/diagnoses'], ['GET', '/api/diagnosis/meta']];
    }

    #[DataProvider('endpoints')]
    public function test_all_diagnosis_endpoints_require_authentication(string $method, string $path): void
    {
        $diagnosis = Diagnosis::factory()->create();
        $this->json($method, strtr($path, ['{d}' => $diagnosis->id, '{u}' => $diagnosis->unit_id]))->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_all_diagnosis_endpoints_enforce_permissions(string $method, string $path): void
    {
        $diagnosis = Diagnosis::factory()->create();
        $this->withPermissions();
        $this->json($method, strtr($path, ['{d}' => $diagnosis->id, '{u}' => $diagnosis->unit_id]))->assertForbidden();
    }

    public function test_registration_sets_unit_actor_multiple_diagnoses_and_has_no_unit_side_effects(): void
    {
        $user = $this->withPermissions(Permission::DiagnosisCreate, Permission::UnitsView);
        $unit = Unit::factory()->create(['status' => UnitStatus::InProcess, 'current_location' => 'Planta 1']);
        $response = $this->postJson('/api/units/'.$unit->id.'/diagnoses', ['notes' => '  Diagnóstico inicial.  '])
            ->assertCreated()->assertJsonPath('data.notes', 'Diagnóstico inicial.')
            ->assertJsonPath('data.unit.vin', $unit->vin)->assertJsonPath('data.defect_count', 0)
            ->assertJsonPath('data.diagnosed_by', ['id' => $user->id, 'name' => $user->name]);
        $this->assertSame(['id', 'vin', 'model'], array_keys($response->json('data.unit')));
        $this->postJson('/api/units/'.$unit->id.'/diagnoses', [])->assertCreated()->assertJsonPath('data.notes', null);
        $this->assertCount(2, $unit->diagnoses);
        $this->assertSame(UnitStatus::InProcess, $unit->fresh()->status);
        $this->assertSame('Planta 1', $unit->fresh()->current_location);
    }

    public function test_creation_requires_access_to_the_unit_context(): void
    {
        $unit = Unit::factory()->create();
        $this->withPermissions(Permission::DiagnosisCreate);
        $this->postJson('/api/units/'.$unit->id.'/diagnoses', [])->assertForbidden();
        $this->withPermissions(Permission::DiagnosisView);
        $this->getJson('/api/units/'.$unit->id.'/diagnoses')->assertForbidden();
    }

    public function test_validation_rejects_forged_identity_html_long_notes_and_empty_updates(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create();
        foreach ([['diagnosed_by' => null], ['unit_id' => $unit->id], ['notes' => '<b>HTML</b>'], ['notes' => str_repeat('a', 5001)]] as $payload) {
            $this->postJson('/api/units/'.$unit->id.'/diagnoses', $payload)->assertUnprocessable()->assertJsonValidationErrors(array_keys($payload));
        }
        $diagnosis = Diagnosis::factory()->for($unit)->create();
        $this->patchJson('/api/diagnoses/'.$diagnosis->id, [])->assertUnprocessable()->assertJsonValidationErrors('record');
        $this->patchJson('/api/diagnoses/'.$diagnosis->id, ['unit_id' => $unit->id])->assertUnprocessable();
        $this->patchJson('/api/diagnoses/'.$diagnosis->id, ['notes' => '  '])->assertOk()->assertJsonPath('data.notes', null);
    }

    public function test_view_and_update_are_distinct_and_resources_are_safe(): void
    {
        $diagnosis = Diagnosis::factory()->create();
        Defect::factory()->for($diagnosis)->create();
        $this->withPermissions(Permission::DiagnosisView);
        $this->getJson('/api/diagnoses')->assertOk()->assertJsonMissingPath('data.0.defects');
        $this->getJson('/api/diagnoses/'.$diagnosis->id)->assertOk()->assertJsonPath('data.defect_count', 1)
            ->assertJsonCount(1, 'data.defects')->assertJsonMissingPath('data.diagnosed_by.password');
        $this->patchJson('/api/diagnoses/'.$diagnosis->id, ['notes' => 'Updated'])->assertForbidden();
        $this->withPermissions(Permission::DiagnosisUpdate);
        $this->patchJson('/api/diagnoses/'.$diagnosis->id, ['notes' => ' Updated '])->assertOk()->assertJsonPath('data.notes', 'Updated');
        $this->getJson('/api/diagnoses')->assertForbidden();
    }

    public function test_pagination_unit_filter_and_deterministic_order(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create();
        $records = Diagnosis::factory()->count(25)->for($unit)->create(['created_at' => now()->startOfDay()]);
        Diagnosis::factory()->create();
        $this->getJson('/api/diagnoses?unit_id='.$unit->id)->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)->assertJsonPath('meta.per_page', 20)->assertJsonPath('data.0.id', $records->last()->id);
        $this->getJson('/api/units/'.$unit->id.'/diagnoses?per_page=10&page=2')->assertOk()
            ->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 25)->assertJsonPath('meta.current_page', 2);
        $this->getJson('/api/diagnoses?sort=id&direction=asc')->assertOk()->assertJsonPath('data.0.id', $records->first()->id);
    }

    public static function searches(): array
    {
        return [['vin'], ['model'], ['notes']];
    }

    #[DataProvider('searches')]
    public function test_search_finds_vin_model_and_notes(string $field): void
    {
        $this->administrator();
        $unit = Unit::factory()->create(['model' => 'Unique model']);
        $target = Diagnosis::factory()->for($unit)->create(['notes' => 'Unique notes']);
        Diagnosis::factory()->create(['notes' => 'Other']);
        $term = match ($field) {
            'vin' => strtolower($unit->vin), 'model' => 'Unique model', 'notes' => 'Unique notes'
        };
        $this->getJson('/api/diagnoses?search='.urlencode($term))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    public static function invalidFilters(): array
    {
        return [['per_page', 101], ['per_page', 0], ['page', -1], ['unit_id', 999999],
            ['sort', 'DROP TABLE diagnoses'], ['direction', 'sideways'], ['search', ['bad']]];
    }

    #[DataProvider('invalidFilters')]
    public function test_bad_filters_return_422(string $key, mixed $value): void
    {
        $this->administrator();
        $this->getJson('/api/diagnoses?'.http_build_query([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public function test_missing_records_and_no_delete_workflow(): void
    {
        $this->administrator();
        $this->getJson('/api/diagnoses/999999')->assertNotFound()->assertJsonMissingPath('trace')->assertJsonMissingPath('file');
        $diagnosis = Diagnosis::factory()->create();
        $this->deleteJson('/api/diagnoses/'.$diagnosis->id)->assertMethodNotAllowed();
    }

    public function test_actor_deletion_preserves_history_and_unit_deletion_is_restricted(): void
    {
        $actor = User::factory()->create();
        $diagnosis = Diagnosis::factory()->create(['diagnosed_by' => $actor->id]);
        $actor->delete();
        $this->assertNull($diagnosis->fresh()->diagnosed_by);
        $this->expectException(QueryException::class);
        $diagnosis->unit->delete();
    }

    public function test_policy_and_super_admin_follow_the_centralized_gate(): void
    {
        $user = User::factory()->create()->assignRole(Role::SuperAdmin);
        $this->assertInstanceOf(DiagnosisPolicy::class, Gate::getPolicyFor(Diagnosis::class));
        $this->actingAs($user, 'web');
        $unit = Unit::factory()->create();
        $this->postJson('/api/units/'.$unit->id.'/diagnoses')->assertCreated();
        $this->assertTrue($user->can(Permission::DiagnosisManageCatalog));
        $this->assertCount(0, $user->getAllPermissions());
    }

    public function test_diagnosis_mutations_preserve_csrf(): void
    {
        $this->administrator();
        $diagnosis = Diagnosis::factory()->create();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->postJson('/api/units/'.$diagnosis->unit_id.'/diagnoses')->assertStatus(419);
        $this->patchJson('/api/diagnoses/'.$diagnosis->id, ['notes' => 'test'])->assertStatus(419);
    }

    public function test_api_failures_do_not_expose_internal_paths_even_when_debug_is_enabled(): void
    {
        $this->administrator();
        config(['app.debug' => true]);
        $diagnosis = Diagnosis::factory()->create();
        Event::listen('eloquent.updating: '.Diagnosis::class, function (): void {
            throw new RuntimeException('Internal failure at /private/server/path');
        });

        try {
            $response = $this->patchJson('/api/diagnoses/'.$diagnosis->id, ['notes' => 'Updated'])
                ->assertStatus(500)->assertExactJson(['message' => 'Internal Server Error']);
            $this->assertStringNotContainsString('/private/server/path', $response->getContent());
        } finally {
            Event::forget('eloquent.updating: '.Diagnosis::class);
        }
    }
}
