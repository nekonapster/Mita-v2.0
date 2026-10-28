<?php

namespace App\Policies;

use App\Models\Incidencia;
use App\Models\Role;
use App\Models\User;

class IncidenciaPolicy
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
        return $user->hasRole([Role::RRHH, Role::PLANNER, Role::ACCOUNTANT, Role::EMPLOYEE]);
    }

    public function view(User $user, Incidencia $incidencia): bool
    {
        if ($user->hasRole([Role::RRHH, Role::PLANNER, Role::ACCOUNTANT])) {
            return true;
        }

        return $user->id === $incidencia->user_id || ($user->employee_id && $user->employee_id === $incidencia->empleado_id);
    }

    public function create(User $user): bool
    {
        return true; // Cualquier usuario autenticado puede registrar una incidencia
    }

    public function update(User $user, Incidencia $incidencia): bool
    {
        return $user->hasRole([Role::RRHH, Role::PLANNER]) || $user->id === $incidencia->user_id;
    }

    public function delete(User $user, Incidencia $incidencia): bool
    {
        return $user->hasRole(Role::RRHH);
    }
}
