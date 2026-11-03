<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\DefectType;
use App\Models\Diagnosis;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\Material;
use App\Models\MaterialConsumption;
use App\Models\Process;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\DefectTypePolicy;
use App\Policies\DiagnosisPolicy;
use App\Policies\InspectionChecklistPolicy;
use App\Policies\InspectionPolicy;
use App\Policies\MaterialConsumptionPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\ProcessPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkOrderPolicy;
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
        Gate::policy(Process::class, ProcessPolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(MaterialConsumption::class, MaterialConsumptionPolicy::class);
        Gate::policy(Inspection::class, InspectionPolicy::class);
        Gate::policy(InspectionChecklist::class, InspectionChecklistPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(WorkOrder::class, WorkOrderPolicy::class);
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
