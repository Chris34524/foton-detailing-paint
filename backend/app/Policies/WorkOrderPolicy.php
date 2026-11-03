<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Diagnosis;
use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkOrdersView);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersView);
    }

    public function create(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can(Permission::WorkOrdersCreate) && $user->can('view', $diagnosis);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersUpdate);
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersAssign);
    }
}
