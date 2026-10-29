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
    public function test_employee_cannot_create_employee_through_livewire(): void
{
    Livewire::actingAs($this->employeeUser)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->set('nombre', 'Prueba')
        ->set('apellidos', 'No Autorizado')
        ->set('categoria_id', $this->empleado->categoria_id)
        ->call('guardar')
        ->assertForbidden();
}

    public function test_employee_cannot_edit_employee_through_livewire(): void
{
    Livewire::actingAs($this->employeeUser)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->call('abrirModalEditar', $this->empleado->id)
        ->set('nombre', 'Cambio no autorizado')
        ->call('guardar')
        ->assertForbidden();

    $this->assertDatabaseHas('empleados', [
        'id' => $this->empleado->id,
        'nombre' => 'Mateo',
    ]);
}

public function test_employee_cannot_delete_another_employee(): void
{
    $victima = Empleado::create([
        'nombre' => 'Elena',
        'apellidos' => 'Protegida',
        'categoria_id' => $this->empleado->categoria_id,
        'activo' => true,
    ]);

    Livewire::actingAs($this->employeeUser)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->call('confirmarEliminar', $victima->id)
        ->assertForbidden();

    $this->assertDatabaseHas('empleados', [
        'id' => $victima->id,
    ]);
}


public function test_employee_cannot_delete_directly_through_livewire(): void
{
    $victima = Empleado::create([
        'nombre' => 'Elena',
        'apellidos' => 'Protegida',
        'categoria_id' => $this->empleado->categoria_id,
        'activo' => true,
    ]);

    Livewire::actingAs($this->employeeUser)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->set('empleadoToDeleteId', $victima->id)
        ->set('tieneHistorialLaboral', false)
        ->call('eliminar')
        ->assertForbidden();

    $this->assertDatabaseHas('empleados', [
        'id' => $victima->id,
    ]);
}


public function test_admin_cannot_delete_employee_with_contract(): void
{
    // Crear empleado
    $empleado = Empleado::create([
        'nombre' => 'Carlos',
        'apellidos' => 'Historial Protegido',
        'categoria_id' => $this->empleado->categoria_id,
        'activo' => true,
    ]);

    // Crear contrato
    $contrato = \App\Models\Contrato::create([
        'empleado_id' => $empleado->id,
        'tipo_contrato' => 'indefinido',
        'fecha_inicio' => '2026-01-01',
        'jornada_semanal_horas' => 40,
    ]);

    // Intentar eliminar manipulando Livewire
    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->set('empleadoToDeleteId', $empleado->id)
        ->set('tieneHistorialLaboral', false)
        ->call('eliminar');

    // El empleado debe seguir existiendo, pero inactivo
    $this->assertDatabaseHas('empleados', [
        'id' => $empleado->id,
        'activo' => false,
    ]);

    // El contrato debe conservarse
    $this->assertDatabaseHas('contratos', [
        'id' => $contrato->id,
        'empleado_id' => $empleado->id,
    ]);
}

public function test_admin_preserves_employee_with_cost_rate(): void
{
    $empleado = Empleado::create([
        'nombre' => 'Laura',
        'apellidos' => 'Tarifa Protegida',
        'categoria_id' => $this->empleado->categoria_id,
        'activo' => true,
    ]);

    $tarifa = \App\Models\TarifaCoste::create([
        'empleado_id' => $empleado->id,
        'coste_hora' => 18.50,
        'fecha_inicio' => '2026-01-01',
    ]);

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->set('empleadoToDeleteId', $empleado->id)
        ->set('tieneHistorialLaboral', false)
        ->call('eliminar');

    // El empleado debe conservarse, pero desactivado
    $this->assertDatabaseHas('empleados', [
        'id' => $empleado->id,
        'activo' => false,
    ]);

    // La tarifa económica debe conservarse
    $this->assertDatabaseHas('tarifas_coste', [
        'id' => $tarifa->id,
        'empleado_id' => $empleado->id,
    ]);
}

public function test_admin_preserves_employee_with_user_account(): void
{
    // Ya tenemos un empleado vinculado a una cuenta
    // desde el método setUp().

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
        ->set('empleadoToDeleteId', $this->empleado->id)
        ->set('tieneHistorialLaboral', false)
        ->call('eliminar');

    // El empleado debe conservarse, pero inactivo.
    $this->assertDatabaseHas('empleados', [
        'id' => $this->empleado->id,
        'activo' => false,
    ]);

    // La cuenta debe seguir vinculada al empleado.
    $this->assertDatabaseHas('users', [
        'id' => $this->employeeUser->id,
        'employee_id' => $this->empleado->id,
    ]);
}

public function test_inactive_employee_can_log_in(): void
{
    $this->empleado->update(['activo' => false]);

    $this->post('/login', [
        'email' => $this->employeeUser->email,
        'password' => 'password123',
    ]);

    $this->assertAuthenticatedAs($this->employeeUser);
}

public function test_blocked_user_cannot_log_in(): void
{
    // Bloqueamos la cuenta, no al empleado
    $this->employeeUser->update(['activo' => false]);

    // Intentamos iniciar sesión
    $this->post('/login', [
        'email' => $this->employeeUser->email,
        'password' => 'password123',
    ]);

    // El usuario debe permanecer sin autenticar
    $this->assertGuest();
}

public function test_blocked_user_loses_active_session(): void
{
    // El empleado inicia sesión normalmente
    $this->post('/login', [
        'email' => $this->employeeUser->email,
        'password' => 'password123',
    ]);

    $this->assertAuthenticatedAs($this->employeeUser);

    // Un administrador bloquea su cuenta
    $this->employeeUser->update(['activo' => false]);

    // El empleado intenta seguir utilizando MiTa
    $this->get('/dashboard')
        ->assertRedirect('/login');

    // Su sesión debe quedar cerrada
    $this->assertGuest();
}

public function test_admin_can_block_and_unblock_user(): void
{
    // Bloquear cuenta
    Livewire::actingAs($this->admin)
        ->test(UserRoleManager::class)
        ->call('cambiarEstadoCuenta', $this->employeeUser->id);

    $this->assertDatabaseHas('users', [
        'id' => $this->employeeUser->id,
        'activo' => false,
    ]);

    // El empleado sigue activo laboralmente
    $this->assertDatabaseHas('empleados', [
        'id' => $this->empleado->id,
        'activo' => true,
    ]);

    // Desbloquear cuenta
    Livewire::actingAs($this->admin)
        ->test(UserRoleManager::class)
        ->call('cambiarEstadoCuenta', $this->employeeUser->id);

    $this->assertDatabaseHas('users', [
        'id' => $this->employeeUser->id,
        'activo' => true,
    ]);
}

public function test_employee_cannot_block_user_accounts(): void
{
    // Autenticarnos como empleado
    $this->actingAs($this->employeeUser);

    // Probar directamente la autorización del método
    try {
        (new UserRoleManager())
            ->cambiarEstadoCuenta($this->admin->id);

        $this->fail('La operación debería devolver 403.');

    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $this->assertSame(403, $e->getStatusCode());
    }

    // Comprobar que la cuenta no se ha bloqueado
    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'activo' => true,
    ]);
}

public function test_admin_cannot_block_own_account(): void
{
    Livewire::actingAs($this->admin)
        ->test(UserRoleManager::class)
        ->call('cambiarEstadoCuenta', $this->admin->id)
        ->assertSet(
            'feedbackError',
            'No puedes modificar el estado de tu propia cuenta.'
        );

    // La cuenta debe permanecer activa.
    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'activo' => true,
    ]);
}

public function test_system_preserves_last_active_admin(): void
{
    // Crear un segundo administrador
    $segundoAdmin = User::create([
        'name' => 'Administrador B',
        'email' => 'adminb@mita.es',
        'password' => bcrypt('password123'),
    ]);

    $segundoAdmin->roles()->attach($this->roleAdmin->id);

    // Administrador A bloquea a B
    Livewire::actingAs($this->admin)
        ->test(UserRoleManager::class)
        ->call('cambiarEstadoCuenta', $segundoAdmin->id);

    $this->assertDatabaseHas('users', [
        'id' => $segundoAdmin->id,
        'activo' => false,
    ]);

    // A intenta bloquear su propia cuenta
    Livewire::actingAs($this->admin)
        ->test(UserRoleManager::class)
        ->call('cambiarEstadoCuenta', $this->admin->id)
        ->assertSet(
            'feedbackError',
            'No puedes modificar el estado de tu propia cuenta.'
        );

    // Debe permanecer al menos un administrador activo
    $administradoresActivos = User::where('activo', true)
        ->whereHas('roles', fn ($q) =>
            $q->where('name', Role::ADMIN)
        )
        ->count();

    $this->assertSame(1, $administradoresActivos);
}

}
