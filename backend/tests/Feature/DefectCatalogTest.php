<?php

namespace Tests\Feature;

use App\Enums\DefectPriority;
use App\Enums\Permission;
use App\Models\Defect;
use App\Models\DefectType;
use Illuminate\Database\QueryException;

class DefectCatalogTest extends DiagnosisTestCase
{
    public function test_catalog_requires_view_and_hides_inactive_entries_from_non_managers(): void
    {
        $active = DefectType::factory()->create();
        DefectType::factory()->create(['is_active' => false]);
        $this->getJson('/api/defect-types')->assertUnauthorized();
        $this->withPermissions();
        $this->getJson('/api/defect-types')->assertForbidden();
        $this->withPermissions(Permission::DiagnosisView);
        $this->getJson('/api/defect-types')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $active->id);
        $this->getJson('/api/defect-types?include_inactive=1')->assertForbidden();
        $this->postJson('/api/defect-types', ['name' => 'Forbidden'])->assertForbidden();
        $this->patchJson('/api/defect-types/'.$active->id, ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_managers_can_create_rename_describe_deactivate_and_reactivate(): void
    {
        $this->withPermissions(Permission::DiagnosisView, Permission::DiagnosisManageCatalog);
        $response = $this->postJson('/api/defect-types', ['name' => ' Rayón de prueba ', 'description' => ' Descripción '])
            ->assertCreated()->assertJsonPath('data.name', 'Rayón de prueba')->assertJsonPath('data.is_active', true);
        $id = $response->json('data.id');
        $this->patchJson('/api/defect-types/'.$id, ['name' => 'Nombre corregido', 'description' => null, 'is_active' => false])
            ->assertOk()->assertJsonPath('data.description', null)->assertJsonPath('data.is_active', false);
        $this->getJson('/api/defect-types')->assertJsonCount(0, 'data');
        $this->getJson('/api/defect-types?include_inactive=1')->assertJsonCount(1, 'data');
        $this->patchJson('/api/defect-types/'.$id, ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->deleteJson('/api/defect-types/'.$id)->assertMethodNotAllowed();
    }

    public function test_name_uniqueness_validation_and_empty_update_rules(): void
    {
        $this->administrator();
        $type = DefectType::factory()->create(['name' => 'Scratch']);
        $this->postJson('/api/defect-types', ['name' => ' scratch '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson('/api/defect-types/'.$type->id, ['name' => ' Scratch '])->assertOk();
        $other = DefectType::factory()->create();
        $this->patchJson('/api/defect-types/'.$other->id, ['name' => 'Scratch'])->assertUnprocessable();
        $this->patchJson('/api/defect-types/'.$other->id, [])->assertUnprocessable();
        $this->postJson('/api/defect-types', ['name' => ' '])->assertUnprocessable();
        $this->postJson('/api/defect-types', ['name' => str_repeat('a', 121), 'description' => str_repeat('a', 2001), 'is_active' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'description', 'is_active']);
    }

    public function test_catalog_pagination_search_and_empty_production_seed_catalog(): void
    {
        $this->administrator();
        $this->getJson('/api/defect-types')->assertJsonCount(0, 'data');
        DefectType::factory()->count(4)->create();
        $target = DefectType::factory()->create(['name' => 'Unique scratch']);
        $this->getJson('/api/defect-types?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5);
        $this->getJson('/api/defect-types?search=Unique')->assertJsonPath('data.0.id', $target->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/defect-types?per_page=101')->assertUnprocessable();
        $this->getJson('/api/defect-types?include_inactive=invalid')->assertUnprocessable();
    }

    public function test_a_used_type_cannot_be_deleted_from_the_database(): void
    {
        $defect = Defect::factory()->create();
        $this->expectException(QueryException::class);
        $defect->defectType->delete();
    }

    public function test_priority_metadata_matches_all_enum_values_and_labels(): void
    {
        $this->withPermissions(Permission::DiagnosisView);
        $this->getJson('/api/diagnosis/meta')->assertOk()->assertExactJson(['priorities' => array_map(
            fn (DefectPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()],
            DefectPriority::cases(),
        )]);
    }
}
