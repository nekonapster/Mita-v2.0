<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\MovimientoEconomico;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rrhh;
    private User $planner;
    private User $accountant;
    private User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create(['name' => Role::ADMIN, 'display_name' => 'Admin']);
        $roleRrhh = Role::create(['name' => Role::RRHH, 'display_name' => 'RRHH']);
        $rolePlanner = Role::create(['name' => Role::PLANNER, 'display_name' => 'Planner']);
        $roleAccountant = Role::create(['name' => Role::ACCOUNTANT, 'display_name' => 'Accountant']);
        $roleEmployee = Role::create(['name' => Role::EMPLOYEE, 'display_name' => 'Employee']);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.es', 'password' => 'secret']);
        $this->admin->roles()->attach($roleAdmin->id);

        $this->rrhh = User::create(['name' => 'RRHH', 'email' => 'rrhh@test.es', 'password' => 'secret']);
        $this->rrhh->roles()->attach($roleRrhh->id);

        $this->planner = User::create(['name' => 'Planner', 'email' => 'planner@test.es', 'password' => 'secret']);
        $this->planner->roles()->attach($rolePlanner->id);

        $this->accountant = User::create(['name' => 'Accountant', 'email' => 'acc@test.es', 'password' => 'secret']);
        $this->accountant->roles()->attach($roleAccountant->id);

        $this->employeeUser = User::create(['name' => 'Emp', 'email' => 'emp@test.es', 'password' => 'secret']);
        $this->employeeUser->roles()->attach($roleEmployee->id);
    }

    public function test_admin_has_unrestricted_gate_access(): void
    {
        $this->assertTrue($this->admin->isAdmin());
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Empleado::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', Turno::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', MovimientoEconomico::class));
    }

    public function test_rrhh_can_manage_employees_but_cannot_create_shifts(): void
    {
        $this->assertTrue(Gate::forUser($this->rrhh)->allows('create', Empleado::class));
        $this->assertFalse(Gate::forUser($this->rrhh)->allows('create', Turno::class));
        $this->assertFalse(Gate::forUser($this->rrhh)->allows('create', MovimientoEconomico::class));
    }

    public function test_planner_can_create_shifts_but_cannot_create_employees(): void
    {
        $this->assertTrue(Gate::forUser($this->planner)->allows('create', Turno::class));
        $this->assertFalse(Gate::forUser($this->planner)->allows('create', Empleado::class));
        $this->assertFalse(Gate::forUser($this->planner)->allows('create', MovimientoEconomico::class));
    }

    public function test_accountant_can_manage_economic_movements(): void
    {
        $this->assertTrue(Gate::forUser($this->accountant)->allows('create', MovimientoEconomico::class));
        $this->assertFalse(Gate::forUser($this->accountant)->allows('create', Turno::class));
        $this->assertFalse(Gate::forUser($this->accountant)->allows('create', Empleado::class));
    }

    public function test_employee_cannot_create_administrative_records(): void
    {
        $this->assertFalse(Gate::forUser($this->employeeUser)->allows('create', Empleado::class));
        $this->assertFalse(Gate::forUser($this->employeeUser)->allows('create', Turno::class));
        $this->assertFalse(Gate::forUser($this->employeeUser)->allows('create', MovimientoEconomico::class));
    }
}
