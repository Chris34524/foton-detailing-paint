<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
        $this->seed(RolePermissionSeeder::class);
        $this->withCredentials();
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_guests_cannot_access_the_current_user_or_logout_even_without_an_accept_header(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->get('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_a_valid_login_returns_the_public_user_contract_and_rotates_the_session(): void
    {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        $user->assignRole(Role::Operator);
        $csrf = $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $oldSessionId = session()->getId();
        $this->continueWithSession($csrf);

        $response = $this->postJson('/api/auth/login', [
            'email' => '  '.Str::upper($user->email).'  ', 'password' => $password,
        ])->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.roles', [Role::Operator->value]);

        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'email', 'roles', 'permissions'], array_keys($response->json('user')),
        );
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => $oldSessionId]);
        $this->assertContains(Permission::MaterialsConsume->value, $response->json('user.permissions'));
        $this->assertNotContains(Permission::UsersManage->value, $response->json('user.permissions'));
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_unknown_email_and_wrong_password_return_the_same_generic_error(): void
    {
        $user = User::factory()->create();
        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => Str::password(),
        ])->assertUnauthorized();
        $unknownEmail = $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.test', 'password' => Str::password(),
        ])->assertUnauthorized();

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertGuest('web');
    }

    public function test_login_validates_required_fields_and_email_format(): void
    {
        $this->postJson('/api/auth/login')->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/auth/login', ['email' => 'invalid', 'password' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_limits_five_failed_attempts_per_normalized_email_and_ip_per_minute(): void
    {
        $payload = ['email' => 'throttled@example.test', 'password' => Str::password()];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [...$payload, 'email' => ' THROTTLED@EXAMPLE.TEST '])
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->postJson('/api/auth/login', [...$payload, 'email' => 'another@example.test'])->assertUnauthorized();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->postJson('/api/auth/login', $payload)->assertUnauthorized();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => Str::password()])
                ->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => Str::password()])
                ->assertUnauthorized();
        }
    }

    public function test_a_session_cookie_restores_the_user_roles_and_direct_and_role_permissions(): void
    {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        $user->assignRole(Role::Inspector);
        $user->givePermissionTo(Permission::UsersView);
        $response = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password]);
        $this->continueWithSession($response);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.roles', [Role::Inspector->value])
            ->assertJsonFragment(['permissions' => [
                'dashboard.view', 'diagnosis.view', 'inspection.execute', 'inspection.view',
                'release.execute', 'traceability.view', 'units.view', 'users.view',
            ]]);
    }

    public function test_super_admin_receives_all_effective_permissions_without_direct_grants(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::SuperAdmin);
        $response = $this->actingAs($user, 'web')->getJson('/api/auth/me')->assertOk();

        $this->assertEqualsCanonicalizing(Permission::values(), $response->json('user.permissions'));
        $this->assertCount(0, $user->getAllPermissions());
    }

    public function test_logout_invalidates_the_session_rotates_csrf_and_prevents_cookie_replay(): void
    {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password]);
        $oldSessionId = $login->getCookie(config('session.cookie'))->getValue();
        $oldCsrfToken = session()->token();
        $this->continueWithSession($login);
        $logout = $this->postJson('/api/auth/logout')->assertOk();

        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldCsrfToken, session()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $oldSessionId]);
        $this->continueWithSession($logout);
        $this->getJson('/api/auth/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->withCookie(config('session.cookie'), $oldSessionId);
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_expired_sessions_are_rejected(): void
    {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password]);
        $this->continueWithSession($login);
        $this->travel(config('session.lifetime') + 1)->minutes();

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_cookie_flow_keeps_csrf_enforced_for_login_and_logout(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        $payload = ['email' => $user->email, 'password' => $password];

        $this->postJson('/api/auth/login', $payload)->assertStatus(419);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $csrf = $this->getJson('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $this->assertSame(session()->token(), $csrf->getCookie('XSRF-TOKEN')->getValue());
        $this->continueWithSession($csrf);
        $login = $this->withHeader('X-XSRF-TOKEN', $csrf->getCookie('XSRF-TOKEN', false)->getValue())
            ->postJson('/api/auth/login', $payload)->assertOk();

        $this->continueWithSession($login);
        $this->withHeader('X-XSRF-TOKEN', 'invalid')->postJson('/api/auth/logout')->assertStatus(419);
        $this->withHeader('X-XSRF-TOKEN', $login->getCookie('XSRF-TOKEN', false)->getValue())
            ->postJson('/api/auth/logout')->assertOk();
    }

    public function test_non_spa_login_requests_require_csrf_instead_of_failing_with_a_missing_session(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->withoutHeader('Origin')->postJson('/api/auth/login')->assertStatus(419);
        $this->withHeader('Origin', 'https://untrusted.example')->postJson('/api/auth/login')->assertStatus(419);
    }

    public function test_cors_allows_only_the_configured_frontend_with_credentials(): void
    {
        foreach (['/api/auth/login', '/sanctum/csrf-cookie'] as $path) {
            $this->withHeaders([
                'Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Content-Type,X-XSRF-TOKEN',
            ])->options($path)->assertNoContent()
                ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
                ->assertHeader('Access-Control-Allow-Credentials', 'true');
            $untrusted = $this->withHeader('Origin', 'https://untrusted.example')->options($path);
            $this->assertNotContains($untrusted->headers->get('Access-Control-Allow-Origin'), [
                '*', 'https://untrusted.example',
            ]);
        }
    }

    public function test_public_health_endpoint_remains_available(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    private function continueWithSession(TestResponse $response): void
    {
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->withCookie(config('session.cookie'), $response->getCookie(config('session.cookie'))->getValue());
    }
}
