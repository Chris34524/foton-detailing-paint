<?php

namespace App\Policies;

use App\Enums\InventoryMovementType;
use App\Enums\Permission;
use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MaterialsView);
    }

    public function view(User $user, InventoryMovement $movement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user, ?InventoryMovementType $type = null): bool
    {
        return $user->can(Permission::MaterialsManage) || ($user->can(Permission::MaterialsConsume) && $type === InventoryMovementType::Exit);
    }
}
