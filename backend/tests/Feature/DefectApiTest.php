<?php

namespace Tests\Feature;

use App\Enums\DefectPriority;
use App\Enums\Permission;
use App\Models\Defect;
use App\Models\DefectType;
use App\Models\Diagnosis;
use App\Models\User;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;

class DefectApiTest extends DiagnosisTestCase
{
    private function payload(DefectType $type): array
    {
        return ['defect_type_id' => $type->id, 'location' => ' Puerta derecha ', 'priority' => 'high', 'description' => ' Rayón profundo. '];
    }

    public function test_guest_and_unauthorized_mutations_are_blocked(): void
    {
        $defect = Defect::factory()->create();
        $path = '/api/diagnoses/'.$defect->diagnosis_id.'/defects';
        $this->postJson($path, $this->payload($defect->defectType))->assertUnauthorized();
        $this->patchJson($path.'/'.$defect->id, ['priority' => 'low'])->assertUnauthorized();
        $this->withPermissions(Permission::DiagnosisCreate);
        $this->postJson($path, $this->payload($defect->defectType))->assertForbidden();
        $this->patchJson($path.'/'.$defect->id, ['priority' => 'low'])->assertForbidden();
    }

    public function test_registration_uses_parent_actor_and_required_physical_location(): void
    {
        $actor = $this->withPermissions(Permission::DiagnosisUpdate);
        $diagnosis = Diagnosis::factory()->create();
        $type = DefectType::factory()->create();
        $this->postJson('/api/diagnoses/'.$diagnosis->id.'/defects', $this->payload($type))
            ->assertCreated()->assertJsonPath('data.diagnosis_id', $diagnosis->id)
            ->assertJsonPath('data.location', 'Puerta derecha')->assertJsonPath('data.description', 'Rayón profundo.')
            ->assertJsonPath('data.priority_label', 'Alta')->assertJsonPath('data.created_by', ['id' => $actor->id, 'name' => $actor->name]);
        $this->assertDatabaseHas('defects', ['diagnosis_id' => $diagnosis->id, 'created_by' => $actor->id, 'location' => 'Puerta derecha']);
    }

    public static function invalidFields(): array
    {
        return [['location', ' '], ['location', str_repeat('a', 161)], ['location', null],
            ['priority', null], ['priority', 'urgent'], ['defect_type_id', null], ['defect_type_id', 999999],
            ['description', str_repeat('a', 5001)], ['description', '<script>alert(1)</script>'],
            ['created_by', 1], ['diagnosis_id', 1]];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_defects_return_validation_errors(string $field, mixed $value): void
    {
        $this->administrator();
        $diagnosis = Diagnosis::factory()->create();
        $type = DefectType::factory()->create();
        $this->postJson('/api/diagnoses/'.$diagnosis->id.'/defects', [...$this->payload($type), $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_required_fields_have_no_silent_defaults(): void
    {
        $this->administrator();
        $diagnosis = Diagnosis::factory()->create();
        $this->postJson('/api/diagnoses/'.$diagnosis->id.'/defects')->assertUnprocessable()
            ->assertJsonValidationErrors(['defect_type_id', 'priority', 'location']);
    }

    public static function priorities(): array
    {
        return array_map(fn ($priority) => [$priority], DefectPriority::cases());
    }

    #[DataProvider('priorities')]
    public function test_all_priorities_work_without_unit_side_effects(DefectPriority $priority): void
    {
        $this->administrator();
        $diagnosis = Diagnosis::factory()->create();
        $type = DefectType::factory()->create();
        $originalStatus = $diagnosis->unit->status;
        $this->postJson('/api/diagnoses/'.$diagnosis->id.'/defects', [...$this->payload($type), 'priority' => $priority->value])
            ->assertCreated()->assertJsonPath('data.priority', $priority->value)->assertJsonPath('data.priority_label', $priority->label());
        $this->assertSame($originalStatus, $diagnosis->unit->fresh()->status);
    }

    public function test_inactive_types_cannot_be_newly_assigned_but_history_remains_editable(): void
    {
        $this->administrator();
        $existing = Defect::factory()->create();
        $existing->defectType->update(['is_active' => false]);
        $otherInactive = DefectType::factory()->create(['is_active' => false]);
        $path = '/api/diagnoses/'.$existing->diagnosis_id.'/defects';
        $this->postJson($path, $this->payload($existing->defectType))->assertUnprocessable()->assertJsonValidationErrors('defect_type_id');
        $this->patchJson($path.'/'.$existing->id, ['defect_type_id' => $otherInactive->id])->assertUnprocessable();
        $this->patchJson($path.'/'.$existing->id, ['location' => 'Cabina', 'priority' => 'critical', 'defect_type_id' => $existing->defect_type_id])
            ->assertOk()->assertJsonPath('data.defect_type.is_active', false)
            ->assertJsonPath('data.location', 'Cabina')->assertJsonPath('data.priority_label', 'Crítica');
        $this->getJson('/api/diagnoses/'.$existing->diagnosis_id)->assertOk()
            ->assertJsonPath('data.defects.0.defect_type.name', $existing->defectType->name);
    }

    public function test_updates_clear_description_validate_empty_and_reject_reparenting(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create(['description' => 'Prior']);
        $path = '/api/diagnoses/'.$defect->diagnosis_id.'/defects/'.$defect->id;
        $this->patchJson($path, [])->assertUnprocessable()->assertJsonValidationErrors('record');
        $this->patchJson($path, ['description' => ' '])->assertOk()->assertJsonPath('data.description', null);
        $this->patchJson($path, ['diagnosis_id' => $defect->diagnosis_id])->assertUnprocessable();
        $this->patchJson($path, ['priority' => 'whatever'])->assertUnprocessable();
    }

    public function test_cross_diagnosis_binding_is_404_and_no_delete_is_exposed(): void
    {
        $this->administrator();
        $defect = Defect::factory()->create();
        $other = Diagnosis::factory()->create();
        $this->patchJson('/api/diagnoses/'.$other->id.'/defects/'.$defect->id, ['priority' => 'low'])->assertNotFound();
        $this->deleteJson('/api/diagnoses/'.$defect->diagnosis_id.'/defects/'.$defect->id)->assertMethodNotAllowed();
    }

    public function test_core_history_cannot_be_deleted_and_actor_references_become_null(): void
    {
        $actor = User::factory()->create();
        $defect = Defect::factory()->create(['created_by' => $actor->id]);
        $actor->delete();
        $this->assertNull($defect->fresh()->created_by);
        $this->expectException(QueryException::class);
        $defect->diagnosis->delete();
    }
}
