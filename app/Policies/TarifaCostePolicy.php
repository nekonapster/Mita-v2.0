<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\TarifaCoste;
use App\Models\User;

class TarifaCostePolicy
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
        return $user->hasRole([Role::ACCOUNTANT, Role::RRHH]);
    }

    public function view(User $user, TarifaCoste $tarifa): bool
    {
        return $user->hasRole([Role::ACCOUNTANT, Role::RRHH]);
    }

    public function create(User $user): bool
    {
        return $user->hasRole([Role::ACCOUNTANT, Role::RRHH]);
    }

    public function update(User $user, TarifaCoste $tarifa): bool
    {
        return $user->hasRole([Role::ACCOUNTANT, Role::RRHH]);
    }

    public function delete(User $user, TarifaCoste $tarifa): bool
    {
        return $user->hasRole([Role::ACCOUNTANT, Role::RRHH]);
    }
}
