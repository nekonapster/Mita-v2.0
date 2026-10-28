<?php

namespace App\Policies;

use App\Models\Proyecto;
use App\Models\Role;
use App\Models\User;

class ProyectoPolicy
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
        return $user->hasRole([Role::PLANNER, Role::RRHH, Role::ACCOUNTANT]);
    }

    public function view(User $user, Proyecto $proyecto): bool
    {
        return $user->hasRole([Role::PLANNER, Role::RRHH, Role::ACCOUNTANT]);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function update(User $user, Proyecto $proyecto): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function delete(User $user, Proyecto $proyecto): bool
    {
        return $user->hasRole(Role::PLANNER);
    }
}
