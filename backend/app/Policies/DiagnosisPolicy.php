<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Diagnosis;
use App\Models\Unit;
use App\Models\User;

class DiagnosisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DiagnosisView);
    }

    public function view(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can(Permission::DiagnosisView);
    }

    public function viewEvidence(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can('view', $diagnosis)
            || ($user->can(Permission::WorkOrdersView) && $diagnosis->workOrder()->exists());
    }

    public function create(User $user, Unit $unit): bool
    {
        return $user->can(Permission::DiagnosisCreate) && $user->can('view', $unit);
    }

    public function update(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can(Permission::DiagnosisUpdate);
    }
}
