<?php

namespace App\Policies;

use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;

class EmpleadoPolicy
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
        return $user->hasRole([Role::RRHH, Role::PLANNER, Role::ACCOUNTANT]);
    }

    public function view(User $user, Empleado $empleado): bool
    {
        if ($user->hasRole([Role::RRHH, Role::PLANNER, Role::ACCOUNTANT])) {
            return true;
        }

        return $user->employee_id === $empleado->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::RRHH);
    }

    public function update(User $user, Empleado $empleado): bool
    {
        return $user->hasRole(Role::RRHH);
    }

    public function delete(User $user, Empleado $empleado): bool
    {
        return $user->hasRole(Role::RRHH);
    }
}
