<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InspectionChecklist;
use App\Models\User;

class InspectionChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InspectionView) || $user->can(Permission::InspectionManage) || $user->can(Permission::InspectionExecute);
    }

    public function view(User $user, InspectionChecklist $checklist): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InspectionManage);
    }

    public function update(User $user, InspectionChecklist $checklist): bool
    {
        return $user->can(Permission::InspectionManage);
    }
}
