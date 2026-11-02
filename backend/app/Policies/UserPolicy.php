<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView);
    }

    public function view(User $user, User $subject): bool
    {
        return $user->can(Permission::UsersView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersManage);
    }

    public function update(User $user, User $subject): bool
    {
        return $user->can(Permission::UsersManage);
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->can(Permission::UsersManage);
    }
}
