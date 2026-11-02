<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_all_initial_roles_and_module_permissions_are_seeded_for_the_session_guard(): void
    {
        $this->assertEqualsCanonicalizing(
            ['super_admin', 'admin', 'supervisor', 'operator', 'inspector'],
            RoleModel::query()->pluck('name')->all(),
        );
        $this->assertDatabaseCount('permissions', 28);

        foreach (Permission::cases() as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission->value, 'guard_name' => 'web']);
        }

        $this->assertSame(['web'], RoleModel::query()->distinct()->pluck('guard_name')->all());
    }

    public function test_seeders_are_idempotent_and_preserve_an_existing_developer_password(): void
    {
        $developer = User::factory()->create(['email' => 'developer@foton-detailing.local']);
        $passwordHash = $developer->password;
        config(['auth.development_user_password' => Str::password()]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 28);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertSame($passwordHash, $developer->fresh()->password);
        $this->assertTrue($developer->fresh()->hasRole(Role::SuperAdmin));
    }

    #[DataProvider('developmentEnvironments')]
    public function test_a_configured_development_user_receives_super_admin_only_in_development_environments(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
        $password = Str::password();
        config(['auth.development_user_password' => $password]);

        $this->seed(DatabaseSeeder::class);

        $developer = User::query()->where('email', 'developer@foton-detailing.local')->sole();
        $this->assertTrue($developer->hasRole(Role::SuperAdmin));
        $this->assertTrue(Hash::check($password, $developer->password));
        $this->assertNotSame($password, $developer->password);
    }

    public static function developmentEnvironments(): array
    {
        return [['local'], ['testing']];
    }

    public function test_no_development_account_is_created_without_an_explicit_password(): void
    {
        config(['auth.development_user_password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_seeding_creates_authorization_data_but_never_privileged_accounts(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['auth.development_user_password' => Str::password()]);

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 28);

        $developer = User::factory()->create(['email' => 'developer@foton-detailing.local']);
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);

        $this->assertFalse($developer->fresh()->hasRole(Role::SuperAdmin));
    }

    #[DataProvider('operationalPermissions')]
    public function test_operational_roles_have_the_required_baseline_and_reject_unrelated_permissions(Role $role, array $allowed, array $denied): void
    {
        $user = User::factory()->create()->assignRole($role);

        foreach ($allowed as $permission) {
            $this->assertTrue($user->can($permission), $role->value.' should allow '.$permission->value);
        }

        foreach ($denied as $permission) {
            $this->assertFalse($user->can($permission), $role->value.' should deny '.$permission->value);
        }
    }

    public static function operationalPermissions(): array
    {
        return [
            'supervisor' => [Role::Supervisor, [
                Permission::UnitsView, Permission::DiagnosisView, Permission::WorkOrdersView,
                Permission::WorkOrdersCreate, Permission::WorkOrdersUpdate, Permission::WorkOrdersAssign,
                Permission::ProcessesView, Permission::TimeTrackingView, Permission::MaterialsView,
                Permission::InspectionView, Permission::TraceabilityView, Permission::TrainingView,
                Permission::DashboardView, Permission::AuditView,
            ], [Permission::UsersManage, Permission::ReleaseExecute, Permission::MaterialsManage]],
            'operator' => [Role::Operator, [
                Permission::UnitsView, Permission::WorkOrdersView, Permission::TimeTrackingUse,
                Permission::MaterialsView, Permission::MaterialsConsume, Permission::TraceabilityView,
                Permission::TrainingView,
            ], [Permission::UsersManage, Permission::ReleaseExecute, Permission::WorkOrdersAssign]],
            'inspector' => [Role::Inspector, [
                Permission::UnitsView, Permission::DiagnosisView, Permission::InspectionView,
                Permission::InspectionExecute, Permission::ReleaseExecute, Permission::TraceabilityView,
                Permission::DashboardView,
            ], [Permission::UsersManage, Permission::MaterialsManage, Permission::WorkOrdersAssign]],
        ];
    }

    public function test_admin_has_all_permissions_and_super_admin_uses_gate_without_permission_assignments(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin);
        $superAdmin = User::factory()->create()->assignRole(Role::SuperAdmin);

        $this->assertCount(0, $superAdmin->getAllPermissions());

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->can($permission));
            $this->assertTrue($superAdmin->can($permission));
        }

        $this->assertFalse($superAdmin->can('undefined.permission'));
    }

    public function test_role_middleware_allows_the_required_role_and_rejects_other_roles(): void
    {
        Route::get('/api/testing/role', fn () => response()->json(['authorized' => true]))
            ->middleware(['auth:sanctum', 'role:'.Role::Admin->value]);

        $this->getJson('/api/testing/role')->assertUnauthorized();
        Auth::forgetGuards();
        $this->actingAs(User::factory()->create()->assignRole(Role::Operator), 'web')
            ->getJson('/api/testing/role')->assertForbidden();
        Auth::forgetGuards();
        $this->actingAs(User::factory()->create()->assignRole(Role::Admin), 'web')
            ->getJson('/api/testing/role')->assertOk();
    }

    public function test_permission_middleware_uses_effective_gate_permissions_and_denies_unauthorized_users(): void
    {
        Route::get('/api/testing/permission', fn () => response()->json(['authorized' => true]))
            ->middleware(['auth:sanctum', 'permission:'.Permission::UsersManage->value]);

        $this->actingAs(User::factory()->create()->assignRole(Role::Operator), 'web')
            ->getJson('/api/testing/permission')->assertForbidden();
        Auth::forgetGuards();
        $this->actingAs(User::factory()->create()->assignRole(Role::Admin), 'web')
            ->getJson('/api/testing/permission')->assertOk();
        Auth::forgetGuards();
        $this->actingAs(User::factory()->create()->assignRole(Role::SuperAdmin), 'web')
            ->getJson('/api/testing/permission')->assertOk();
    }

    public function test_role_or_permission_middleware_accepts_either_role_or_permission(): void
    {
        Route::get('/api/testing/role-or-permission', fn () => response()->json(['authorized' => true]))
            ->middleware(['auth:sanctum', 'role_or_permission:'.Role::Operator->value.'|'.Permission::ReleaseExecute->value]);

        foreach ([Role::Operator, Role::Inspector] as $role) {
            Auth::forgetGuards();
            $this->actingAs(User::factory()->create()->assignRole($role), 'web')
                ->getJson('/api/testing/role-or-permission')->assertOk();
        }

        Auth::forgetGuards();
        $this->actingAs(User::factory()->create()->assignRole(Role::Supervisor), 'web')
            ->getJson('/api/testing/role-or-permission')->assertForbidden();
    }

    public function test_user_policy_is_registered_and_enforces_view_and_manage_permissions(): void
    {
        $subject = User::factory()->create();
        $viewer = User::factory()->create()->givePermissionTo(Permission::UsersView);
        $manager = User::factory()->create()->givePermissionTo(Permission::UsersManage);
        $unprivileged = User::factory()->create();
        $superAdmin = User::factory()->create()->assignRole(Role::SuperAdmin);

        $this->assertInstanceOf(UserPolicy::class, Gate::getPolicyFor(User::class));
        $this->assertTrue(Gate::forUser($viewer)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($viewer)->allows('view', $subject));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', $subject));
        $this->assertFalse(Gate::forUser($manager)->allows('view', $subject));

        foreach (['create', 'update', 'delete'] as $ability) {
            $target = $ability === 'create' ? User::class : $subject;
            $this->assertTrue(Gate::forUser($manager)->allows($ability, $target));
            $this->assertTrue(Gate::forUser($superAdmin)->allows($ability, $target));
            $this->assertFalse(Gate::forUser($unprivileged)->allows($ability, $target));
        }

        $this->assertFalse(Gate::forUser($unprivileged)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($unprivileged)->allows('view', $subject));
    }
}
