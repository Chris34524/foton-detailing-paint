<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')->syncPermissions(
                array_map(fn (Permission $permission): string => $permission->value, $this->permissionsFor($role)),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<Permission> */
    private function permissionsFor(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => [],
            Role::Admin => Permission::cases(),
            Role::Supervisor => [
                Permission::UnitsView,
                Permission::DiagnosisView,
                Permission::WorkOrdersView,
                Permission::WorkOrdersCreate,
                Permission::WorkOrdersUpdate,
                Permission::WorkOrdersAssign,
                Permission::ProcessesView,
                Permission::TimeTrackingView,
                Permission::MaterialsView,
                Permission::InspectionView,
                Permission::TraceabilityView,
                Permission::TrainingView,
                Permission::DashboardView,
                Permission::AuditView,
            ],
            Role::Operator => [
                Permission::UnitsView,
                Permission::WorkOrdersView,
                Permission::TimeTrackingUse,
                Permission::MaterialsView,
                Permission::MaterialsConsume,
                Permission::TraceabilityView,
                Permission::TrainingView,
            ],
            Role::Inspector => [
                Permission::UnitsView,
                Permission::DiagnosisView,
                Permission::InspectionView,
                Permission::InspectionExecute,
                Permission::ReleaseExecute,
                Permission::TraceabilityView,
                Permission::DashboardView,
            ],
        };
    }
}
