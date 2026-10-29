<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Turno;
use App\Models\User;

class TurnoPolicy
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
        return $user->hasRole([Role::PLANNER, Role::RRHH, Role::ACCOUNTANT, Role::EMPLOYEE]);
    }

    public function view(User $user, Turno $turno): bool
    {
        if ($user->hasRole([Role::PLANNER, Role::RRHH, Role::ACCOUNTANT])) {
            return true;
        }

        if ($user->hasRole(Role::EMPLOYEE) && $user->employee_id) {
            return $turno->asignacionesActivas()->where('empleado_id', $user->employee_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function update(User $user, Turno $turno): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function delete(User $user, Turno $turno): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function assign(User $user, Turno $turno): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function publish(\App\Models\User $user): bool
    {
        return $user->hasRole(\App\Models\Role::PLANNER);
    }
}
