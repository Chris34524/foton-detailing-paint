<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Material;
use App\Models\User;

class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MaterialsView);
    }

    public function view(User $user, Material $record): bool
    {
        return $user->can(Permission::MaterialsView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MaterialsManage);
    }

    public function update(User $user, Material $record): bool
    {
        return $user->can(Permission::MaterialsManage);
    }
}
