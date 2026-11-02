<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UnitsView);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->can(Permission::UnitsView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UnitsCreate);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can(Permission::UnitsUpdate);
    }
}
