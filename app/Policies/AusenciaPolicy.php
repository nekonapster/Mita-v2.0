<?php

namespace App\Policies;

use App\Models\Ausencia;
use App\Models\Role;
use App\Models\User;

class AusenciaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole([Role::RRHH, Role::PLANNER, Role::EMPLOYEE]);
    }

    public function view(User $user, Ausencia $ausencia): bool
    {
        if ($user->hasRole([Role::RRHH, Role::PLANNER])) {
            return true;
        }

        return $user->employee_id === $ausencia->empleado_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([Role::RRHH, Role::EMPLOYEE]);
    }

    public function approve(User $user, Ausencia $ausencia): bool
    {
        return $user->hasRole(Role::RRHH);
    }

    public function reject(User $user, Ausencia $ausencia): bool
    {
        return $user->hasRole(Role::RRHH);
    }
}
