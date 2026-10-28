<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserRoleManager;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RouteAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employeeUser;
    private Empleado $empleado;
    private Role $roleAdmin;
    private Role $roleEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::create([
            'name' => Role::ADMIN,
            'display_name' => 'Administrador',
            'description' => 'Acceso total al sistema',
        ]);

        $this->roleEmployee = Role::create([
            'name' => Role::EMPLOYEE,
            'display_name' => 'Empleado',
            'description' => 'Acceso al portal personal',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista Acuático',
            'codigo' => 'SOC',
            'activo' => true,
        ]);

        $this->empleado = Empleado::create([
            'nombre' => 'Mateo',
            'apellidos' => 'Vázquez',
            'dni_nie' => '12345678Z',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@mita.es',
            'password' => bcrypt('password123'),
        ]);
        $this->admin->roles()->attach($this->roleAdmin->id);

        $this->employeeUser = User::create([
            'name' => 'Mateo Usuario',
            'email' => 'mateo@mita.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);
        $this->employeeUser->roles()->attach($this->roleEmployee->id);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $responseRoot = $this->get('/');
        $responseRoot->assertRedirect('/login');
    }

    public function test_authenticated_admin_can_access_dashboard_and_admin_users(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertStatus(200);

        $responseAdmin = $this->actingAs($this->admin)->get('/admin/usuarios');
        $responseAdmin->assertStatus(200);
    }

    public function test_employee_cannot_access_admin_users(): void
    {
        $response = $this->actingAs($this->employeeUser)->get('/admin/usuarios');
        $response->assertStatus(403);
    }

    public function test_admin_can_create_user_and_assign_roles_and_link_employee(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalCrear')
            ->set('name', 'Lucía Sánchez')
            ->set('email', 'lucia@mita.es')
            ->set('password', 'secret1234')
            ->set('password_confirmation', 'secret1234')
            ->set('selectedRoles', [$this->roleEmployee->id])
            ->set('employee_id', $this->empleado->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Lucía Sánchez',
            'email' => 'lucia@mita.es',
            'employee_id' => $this->empleado->id,
        ]);

        $newUser = User::where('email', 'lucia@mita.es')->first();
        $this->assertTrue($newUser->hasRole(Role::EMPLOYEE));
    }

    public function test_admin_cannot_delete_self_or_only_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('confirmarEliminar', $this->admin->id)
            ->assertSet('modalDeleteOpen', false)
            ->assertSet('feedbackError', 'No puede eliminar su propia cuenta de usuario conectada.');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
        ]);
    }

    public function test_employee_can_access_employee_portal(): void
    {
        $response = $this->actingAs($this->employeeUser)->get('/portal/empleado');
        $response->assertStatus(200);
    }

        public function test_employee_cannot_access_hr_employee_list(): void
    {
        $this->actingAs($this->employeeUser)
            ->get('/rrhh/empleados')
            ->assertForbidden();
    }
}
