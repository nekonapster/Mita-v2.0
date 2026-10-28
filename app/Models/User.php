<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'employee_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'user_id');
    }

    public function hasRole(string|array $roleNames): bool
    {
        $roleNames = (array) $roleNames;

        return $this->roles->pluck('name')->intersect($roleNames)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isRrhh(): bool
    {
        return $this->hasRole([Role::ADMIN, Role::RRHH]);
    }

    public function isPlanner(): bool
    {
        return $this->hasRole([Role::ADMIN, Role::PLANNER]);
    }

    public function isAccountant(): bool
    {
        return $this->hasRole([Role::ADMIN, Role::ACCOUNTANT]);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::EMPLOYEE);
    }
}
