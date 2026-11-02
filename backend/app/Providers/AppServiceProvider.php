<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\DefectType;
use App\Models\Diagnosis;
use App\Models\Unit;
use App\Models\User;
use App\Policies\DefectTypePolicy;
use App\Policies\DiagnosisPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Diagnosis::class, DiagnosisPolicy::class);
        Gate::policy(DefectType::class, DefectTypePolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            return Permission::tryFrom($ability) !== null && $user->hasRole(Role::SuperAdmin)
                ? true
                : null;
        });
    }
}
