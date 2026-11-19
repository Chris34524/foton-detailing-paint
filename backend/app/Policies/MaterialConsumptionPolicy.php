<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WorkOrder;

class MaterialConsumptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MaterialsView);
    }

    public function create(User $user, WorkOrder $order): bool
    {
        return $user->can(Permission::MaterialsConsume) && $user->can('view', $order);
    }
}
