<?php

namespace App\Policies;

use App\Models\RegistroHora;
use App\Models\Role;
use App\Models\User;

class RegistroHoraPolicy
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
        return $user->hasRole([Role::ACCOUNTANT, Role::PLANNER, Role::RRHH, Role::EMPLOYEE]);
    }

    public function view(User $user, RegistroHora $registro): bool
    {
        if ($user->hasRole([Role::ACCOUNTANT, Role::PLANNER, Role::RRHH])) {
            return true;
        }

        return $user->employee_id && $user->employee_id === $registro->asignacion?->empleado_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([Role::PLANNER, Role::ACCOUNTANT, Role::EMPLOYEE]);
    }

    public function update(User $user, RegistroHora $registro): bool
    {
        if ($user->hasRole([Role::ACCOUNTANT, Role::PLANNER])) {
            return true;
        }

        // Un empleado solo puede editar sus horas si aún están en revisión (no validadas)
        if ($user->hasRole(Role::EMPLOYEE) && $user->employee_id === $registro->asignacion?->empleado_id) {
            return $registro->estado === RegistroHora::ESTADO_PENDIENTE;
        }

        return false;
    }

    public function validate(User $user, RegistroHora $registro): bool
    {
        return $user->hasRole([Role::ACCOUNTANT, Role::PLANNER]);
    }
}
