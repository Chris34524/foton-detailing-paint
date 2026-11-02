<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Models\User;
use App\Policies\UnitPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitApiTest extends TestCase
{
    use RefreshDatabase;

    private const VIN = 'LVBV3JBB7RA123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Admin);
        $this->actingAs($user, 'web');

        return $user;
    }

    public static function endpoints(): array
    {
        return [
            ['GET', '/api/units'], ['POST', '/api/units'],
            ['GET', '/api/units/meta'], ['GET', '/api/units/by-vin/'.self::VIN],
            ['GET', '/api/units/{id}'], ['PATCH', '/api/units/{id}'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_guests_cannot_access_any_unit_endpoint(string $method, string $path): void
    {
        $unit = Unit::factory()->create(['vin' => self::VIN]);
        $this->json($method, str_replace('{id}', $unit->id, $path))->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_authenticated_users_without_permissions_receive_403(string $method, string $path): void
    {
        $unit = Unit::factory()->create(['vin' => self::VIN]);
        $this->actingAs(User::factory()->create(), 'web')
            ->json($method, str_replace('{id}', $unit->id, $path))->assertForbidden();
    }

    public function test_registration_sets_creator_default_status_and_a_public_resource(): void
    {
        $creator = $this->administrator();
        $response = $this->postJson('/api/units', [
            'vin' => ' lvbv3jbb7ra123456 ', 'model' => ' S5 ', 'current_location' => ' Recepción ',
        ])->assertCreated()->assertJsonPath('data.vin', self::VIN)
            ->assertJsonPath('data.status', 'registered')->assertJsonPath('data.status_label', 'Registrada')
            ->assertJsonPath('data.model', 'S5')->assertJsonPath('data.current_location', 'Recepción')
            ->assertJsonPath('data.registered_by', ['id' => $creator->id, 'name' => $creator->name]);
        $this->assertEqualsCanonicalizing(
            ['id', 'vin', 'model', 'status', 'status_label', 'current_location', 'registered_by', 'created_at', 'updated_at'],
            array_keys($response->json('data')),
        );
        $this->assertDatabaseHas('units', ['vin' => self::VIN, 'created_by' => $creator->id, 'status' => 'registered']);
    }

    public function test_optional_registration_fields_can_be_omitted_or_blank(): void
    {
        $this->administrator();
        $this->postJson('/api/units', ['vin' => self::VIN, 'model' => ' ', 'current_location' => ' '])
            ->assertCreated()->assertJsonPath('data.model', null)->assertJsonPath('data.current_location', null);
    }

    public static function invalidVins(): array
    {
        return [
            [null], [''], ['LVBV3JBB7RA12345'], ['LVBV3JBB7RA1234567'],
            ['LVBV3JBB7RA12345I'], ['LVBV3JBB7RA12345O'], ['LVBV3JBB7RA12345Q'],
            ['LVBV3JBB7RA12345-'], ['LVBV3JBB RA123456'], ['LVBV3JBB7RA12345Á'], [123], [['invalid']],
        ];
    }

    #[DataProvider('invalidVins')]
    public function test_invalid_vins_are_rejected(mixed $vin): void
    {
        $this->administrator();
        $this->postJson('/api/units', ['vin' => $vin])->assertUnprocessable()->assertJsonValidationErrors('vin');
        $this->assertDatabaseCount('units', 0);
    }

    public function test_duplicate_vins_use_the_canonical_representation(): void
    {
        $this->administrator();
        Unit::factory()->create(['vin' => self::VIN]);
        $this->postJson('/api/units', ['vin' => strtolower(self::VIN)])->assertUnprocessable()
            ->assertJsonValidationErrors('vin');
        $this->assertDatabaseCount('units', 1);
    }

    public function test_a_concurrent_duplicate_is_mapped_to_a_validation_error(): void
    {
        $this->administrator();
        Event::listen('eloquent.creating: '.Unit::class, function (Unit $unit): void {
            DB::table('units')->insert(['vin' => $unit->vin, 'status' => 'registered']);
        });
        try {
            $this->postJson('/api/units', ['vin' => self::VIN])->assertUnprocessable()->assertJsonValidationErrors('vin');
        } finally {
            Event::forget('eloquent.creating: '.Unit::class);
        }
    }

    public function test_registration_rejects_client_supplied_status_or_creator_including_null(): void
    {
        $this->administrator();
        foreach ([['status' => 'released'], ['status' => null], ['created_by' => 1], ['created_by' => null]] as $extra) {
            $this->postJson('/api/units', ['vin' => self::VIN, ...$extra])
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($extra));
        }
    }

    public function test_lengths_and_types_are_validated(): void
    {
        $this->administrator();
        $this->postJson('/api/units', [
            'vin' => self::VIN, 'model' => str_repeat('a', 101), 'current_location' => str_repeat('a', 121),
        ])->assertUnprocessable()->assertJsonValidationErrors(['model', 'current_location']);
        $unit = Unit::factory()->create();
        $this->patchJson('/api/units/'.$unit->id, ['model' => [], 'current_location' => 12])
            ->assertUnprocessable()->assertJsonValidationErrors(['model', 'current_location']);
    }

    public function test_vin_lookup_is_case_insensitive_and_missing_units_are_404(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create(['vin' => self::VIN]);
        $this->getJson('/api/units/by-vin/'.strtolower(self::VIN))->assertOk()->assertJsonPath('data.id', $unit->id);
        $this->getJson('/api/units/by-vin/LVBV3JBB7RA999999')->assertNotFound();
        $this->getJson('/api/units/by-vin/invalid')->assertUnprocessable()->assertJsonValidationErrors('vin');
        $this->getJson('/api/units/999999')->assertNotFound();
    }

    public function test_list_uses_server_pagination_defaults_and_keeps_filter_links(): void
    {
        $this->administrator();
        Unit::factory()->count(25)->create(['model' => 'S5']);
        $this->getJson('/api/units')->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)->assertJsonPath('meta.per_page', 20)
            ->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson('/api/units?model=S5&per_page=10&page=2')->assertOk()
            ->assertJsonCount(10, 'data')->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10);
        $this->getJson('/api/units?per_page=100')->assertOk()->assertJsonCount(25, 'data');
    }

    public static function invalidFilters(): array
    {
        return [
            ['per_page', '101'], ['per_page', '0'], ['per_page', 'no'], ['page', '-1'],
            ['status', 'whatever'], ['sort', 'id desc; DROP TABLE units'], ['direction', 'sideways'],
            ['vin', 'INVALID'], ['search', ['invalid']], ['model', str_repeat('a', 101)],
            ['location', str_repeat('a', 121)],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_422(string $key, mixed $value): void
    {
        $this->administrator();
        $this->getJson('/api/units?'.http_build_query([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public static function filters(): array
    {
        return [
            ['vin', strtolower(self::VIN)], ['model', 'S5'], ['status', 'in_process'],
            ['location', 'Planta 1'], ['search', 'LVBV3JBB7RA123456'], ['search', 'S5'], ['search', 'Planta 1'],
        ];
    }

    #[DataProvider('filters')]
    public function test_filters_find_only_matching_units(string $key, string $value): void
    {
        $this->administrator();
        $target = Unit::factory()->create(['vin' => self::VIN, 'model' => 'S5', 'status' => UnitStatus::InProcess, 'current_location' => 'Planta 1']);
        Unit::factory()->create(['model' => 'TM', 'status' => UnitStatus::Registered, 'current_location' => 'Recepción']);
        $this->getJson('/api/units?'.http_build_query([$key => $value]))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    public function test_general_search_is_grouped_with_other_filters(): void
    {
        $this->administrator();
        Unit::factory()->create(['model' => 'S5', 'status' => UnitStatus::Registered]);
        $unit = Unit::factory()->create(['model' => 'S5', 'status' => UnitStatus::InProcess]);
        $this->getJson('/api/units?search=S5&status=in_process')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $unit->id);
    }

    public function test_ordering_is_deterministic_and_supports_whitelisted_fields(): void
    {
        $this->administrator();
        $first = Unit::factory()->create(['vin' => self::VIN, 'created_at' => now()->startOfDay()]);
        $second = Unit::factory()->create(['vin' => 'LVBV3JBB7RA123457', 'created_at' => $first->created_at]);
        $this->getJson('/api/units')->assertOk()->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/units?sort=vin&direction=asc')->assertOk()->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/units?sort=vin&direction=desc')->assertOk()->assertJsonPath('data.0.id', $second->id);
    }

    public function test_unit_detail_is_readable_and_hides_creator_credentials(): void
    {
        $creator = $this->administrator();
        $unit = Unit::factory()->make(['vin' => self::VIN]);
        $unit->registeredBy()->associate($creator);
        $unit->save();
        $response = $this->getJson('/api/units/'.$unit->id)->assertOk()->assertJsonPath('data.vin', self::VIN);
        $this->assertSame(['id', 'name'], array_keys($response->json('data.registered_by')));
        $this->assertStringNotContainsString($creator->password, $response->getContent());
    }

    public function test_updates_allow_every_defined_status_and_trim_or_clear_optional_fields(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create();
        foreach (UnitStatus::cases() as $status) {
            $this->patchJson('/api/units/'.$unit->id, ['status' => $status->value])
                ->assertOk()->assertJsonPath('data.status', $status->value)->assertJsonPath('data.status_label', $status->label());
        }
        $this->patchJson('/api/units/'.$unit->id, ['model' => ' S6 ', 'current_location' => ' Planta 1 - Detallado '])
            ->assertOk()->assertJsonPath('data.model', 'S6')->assertJsonPath('data.current_location', 'Planta 1 - Detallado');
        $this->getJson('/api/units/'.$unit->id)->assertJsonPath('data.current_location', 'Planta 1 - Detallado');
        $this->patchJson('/api/units/'.$unit->id, ['model' => null, 'current_location' => ' '])
            ->assertOk()->assertJsonPath('data.model', null)->assertJsonPath('data.current_location', null);
    }

    public function test_updates_reject_vin_creator_invalid_status_and_empty_payloads(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create();
        foreach ([
            ['vin' => self::VIN], ['vin' => null], ['created_by' => null],
            ['status' => 'whatever'], ['status' => null],
        ] as $payload) {
            $this->patchJson('/api/units/'.$unit->id, $payload)->assertUnprocessable()->assertJsonValidationErrors(array_keys($payload));
        }
        $this->patchJson('/api/units/'.$unit->id, [])->assertUnprocessable()->assertJsonValidationErrors('unit');
        $this->patchJson('/api/units/'.$unit->id, ['unknown' => 'value'])->assertUnprocessable();
    }

    public function test_meta_uses_all_authoritative_enum_options(): void
    {
        $this->administrator();
        $expected = array_map(fn (UnitStatus $status): array => ['value' => $status->value, 'label' => $status->label()], UnitStatus::cases());
        $this->getJson('/api/units/meta')->assertOk()->assertExactJson(['statuses' => $expected]);
    }

    public function test_individual_permissions_do_not_grant_unrelated_operations(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::UnitsView);
        $unit = Unit::factory()->create();
        $this->actingAs($viewer, 'web')->getJson('/api/units')->assertOk();
        $this->getJson('/api/units/'.$unit->id)->assertOk();
        $this->postJson('/api/units', ['vin' => self::VIN])->assertForbidden();
        $this->patchJson('/api/units/'.$unit->id, ['model' => 'S5'])->assertForbidden();
        $creator = User::factory()->create();
        $creator->givePermissionTo(Permission::UnitsCreate);
        Auth::forgetGuards();
        $this->actingAs($creator, 'web')->postJson('/api/units', ['vin' => self::VIN])->assertCreated();
        $this->getJson('/api/units')->assertForbidden();
        $updater = User::factory()->create();
        $updater->givePermissionTo(Permission::UnitsUpdate);
        Auth::forgetGuards();
        $this->actingAs($updater, 'web')->patchJson('/api/units/'.$unit->id, ['status' => 'blocked'])->assertOk();
    }

    public function test_policy_and_super_admin_reuse_the_phase_two_gate_bypass(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::SuperAdmin);
        $this->assertInstanceOf(UnitPolicy::class, Gate::getPolicyFor(Unit::class));
        $this->actingAs($user, 'web')->postJson('/api/units', ['vin' => self::VIN])->assertCreated();
        $unit = Unit::query()->sole();
        $this->getJson('/api/units')->assertOk();
        $this->patchJson('/api/units/'.$unit->id, ['status' => 'released'])->assertOk();
        $this->assertCount(0, $user->getAllPermissions());
    }

    public function test_unit_writes_keep_sanctum_csrf_protection(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create();
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->postJson('/api/units', ['vin' => self::VIN])->assertStatus(419);
        $this->patchJson('/api/units/'.$unit->id, ['status' => 'blocked'])->assertStatus(419);
    }

    public function test_units_have_no_delete_endpoint(): void
    {
        $this->administrator();
        $unit = Unit::factory()->create();
        $this->deleteJson('/api/units/'.$unit->id)->assertMethodNotAllowed();
        $this->assertModelExists($unit);
    }
}
