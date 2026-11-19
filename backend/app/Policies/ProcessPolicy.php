<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Process;
use App\Models\User;

class ProcessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProcessesView);
    }

    public function view(User $user, Process $record): bool
    {
        return $user->can(Permission::ProcessesView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProcessesManage);
    }

    public function update(User $user, Process $record): bool
    {
        return $user->can(Permission::ProcessesManage);
    }
}
