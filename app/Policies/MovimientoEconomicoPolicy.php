<?php

namespace App\Policies;

use App\Models\MovimientoEconomico;
use App\Models\Role;
use App\Models\User;

class MovimientoEconomicoPolicy
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
        return $user->hasRole(Role::ACCOUNTANT);
    }

    public function view(User $user, MovimientoEconomico $movimiento): bool
    {
        return $user->hasRole(Role::ACCOUNTANT);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ACCOUNTANT);
    }

    public function update(User $user, MovimientoEconomico $movimiento): bool
    {
        return $user->hasRole(Role::ACCOUNTANT);
    }

    public function delete(User $user, MovimientoEconomico $movimiento): bool
    {
        return $user->hasRole(Role::ACCOUNTANT);
    }
}
