<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InspectionView);
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $user->can(Permission::InspectionView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InspectionExecute);
    }

    public function update(User $user, Inspection $inspection): bool
    {
        return $user->can(Permission::InspectionExecute);
    }

    public function complete(User $user, Inspection $inspection): bool
    {
        return $user->can(Permission::InspectionExecute);
    }

    public function release(User $user, Inspection $inspection): bool
    {
        return $user->can(Permission::ReleaseExecute);
    }
}
