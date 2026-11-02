<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

abstract class DiagnosisTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    protected function administrator(): User
    {
        Auth::forgetGuards();
        $user = User::factory()->create()->assignRole(Role::Admin);
        $this->actingAs($user, 'web');

        return $user;
    }

    protected function withPermissions(Permission ...$permissions): User
    {
        Auth::forgetGuards();
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $this->actingAs($user, 'web');

        return $user;
    }
}
