<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DefectType;
use App\Models\User;

class DefectTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DiagnosisView);
    }

    public function viewInactive(User $user): bool
    {
        return $user->can(Permission::DiagnosisManageCatalog);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DiagnosisManageCatalog);
    }

    public function update(User $user, DefectType $defectType): bool
    {
        return $user->can(Permission::DiagnosisManageCatalog);
    }
}
