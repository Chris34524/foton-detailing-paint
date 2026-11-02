<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $developer = User::query()->where('email', 'developer@foton-detailing.local')->first();
        $password = config('auth.development_user_password');

        if ($developer === null && is_string($password) && $password !== '') {
            $developer = User::query()->create([
                'email' => 'developer@foton-detailing.local',
                'name' => 'Development User',
                'password' => $password,
            ]);
        }

        $developer?->assignRole(Role::SuperAdmin);
    }
}
