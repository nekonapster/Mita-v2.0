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
            ->whereHas(
                'roles',
                fn($q) =>
                $q->where('name', Role::ADMIN)
            )
            ->count();

        $this->assertSame(1, $administradoresActivos);
    }


    public function test_employee_cannot_publish_shifts(): void
    {
        Livewire::actingAs($this->employeeUser)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->call('publicarBorradores')
            ->assertForbidden();
    }

    public function publish(User $user): bool
    {
        return $user->hasRole(Role::PLANNER);
    }

    public function test_employee_cannot_access_hr_employee_file(): void
    {
        // Un empleado normal intenta entrar en la ficha de RR. HH.
        $this->actingAs($this->employeeUser)
            ->get(route('rrhh.empleados.show', [
                'id' => $this->empleado->id,
            ]))
            ->assertForbidden();

        // El administrador sí debe poder acceder.
        $this->actingAs($this->admin)
            ->get(route('rrhh.empleados.show', [
                'id' => $this->empleado->id,
            ]))
            ->assertOk();
    }

    public function test_planner_cannot_create_employee_contract(): void
    {
        // Crear usuario con rol planificador.
        $rolePlanner = Role::create([
            'name' => Role::PLANNER,
            'display_name' => 'Planificador',
            'description' => 'Gestión de planificación',
        ]);

        $planner = User::create([
            'name' => 'Usuario Planificador',
            'email' => 'planner@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rolePlanner->id);

        // Intentar crear un contrato desde Livewire.
        Livewire::actingAs($planner)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->set('fecha_inicio', '2026-10-01')
            ->call('guardarContrato')
            ->assertForbidden();

        // Verificar que no se haya creado el contrato.
        $this->assertDatabaseMissing('contratos', [
            'empleado_id' => $this->empleado->id,
            'fecha_inicio' => '2026-10-01',
        ]);
    }

    public function test_planner_cannot_delete_employee_contract(): void
    {
        // Crear un contrato existente.
        $contrato = \App\Models\Contrato::create([
            'empleado_id' => $this->empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'jornada_semanal_horas' => 40,
        ]);

        // Crear usuario planificador.
        $rolePlanner = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            [
                'display_name' => 'Planificador',
                'description' => 'Gestión de planificación',
            ]
        );

        $planner = User::create([
            'name' => 'Planificador Prueba',
            'email' => 'planner-delete@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rolePlanner->id);

        // Intentar eliminar el contrato desde Livewire.
        $resultado = Livewire::actingAs($planner)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->call('eliminarContrato', $contrato->id);

        // El contrato debe seguir existiendo.
        $this->assertDatabaseHas('contratos', [
            'id' => $contrato->id,
            'empleado_id' => $this->empleado->id,
        ]);

        // El planificador debe recibir un 403.
        $resultado->assertForbidden();
    }

    public function test_planner_cannot_create_employee_absence(): void
    {
        // Crear usuario planificador.
        $rolePlanner = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            [
                'display_name' => 'Planificador',
                'description' => 'Gestión de planificación',
            ]
        );

        $planner = User::create([
            'name' => 'Planificador Ausencias',
            'email' => 'planner-ausencia@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rolePlanner->id);

        // Intentar registrar unas vacaciones desde Livewire.
        $resultado = Livewire::actingAs($planner)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->set('tipo_ausencia', 'vacaciones')
            ->set('fecha_hora_inicio', '2026-11-02 09:00:00')
            ->set('fecha_hora_fin', '2026-11-06 18:00:00')
            ->call('guardarAusencia');

        // No debe registrarse ninguna ausencia.
        $this->assertDatabaseMissing('ausencias', [
            'empleado_id' => $this->empleado->id,
            'tipo' => 'vacaciones',
            'estado' => \App\Models\Ausencia::ESTADO_APROBADA,
        ]);

        // El planificador debe recibir un 403.
        $resultado->assertForbidden();
    }

    public function test_planner_cannot_delete_employee_absence(): void
    {
        // Crear una ausencia aprobada.
        $ausencia = \App\Models\Ausencia::create([
            'empleado_id' => $this->empleado->id,
            'tipo' => 'vacaciones',
            'fecha_hora_inicio' => '2026-11-02 09:00:00',
            'fecha_hora_fin' => '2026-11-06 18:00:00',
            'estado' => \App\Models\Ausencia::ESTADO_APROBADA,
            'aprobado_por' => $this->admin->id,
            'aprobado_en' => now(),
        ]);

        // Crear usuario planificador.
        $rolePlanner = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            [
                'display_name' => 'Planificador',
                'description' => 'Gestión de planificación',
            ]
        );

        $planner = User::create([
            'name' => 'Planificador Ausencias',
            'email' => 'planner-delete-ausencia@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rolePlanner->id);

        // Intentar eliminar la ausencia desde Livewire.
        $resultado = Livewire::actingAs($planner)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->call('eliminarAusencia', $ausencia->id);

        // La ausencia debe permanecer intacta.
        $this->assertDatabaseHas('ausencias', [
            'id' => $ausencia->id,
            'empleado_id' => $this->empleado->id,
            'estado' => \App\Models\Ausencia::ESTADO_APROBADA,
        ]);

        // El planificador debe recibir un 403.
        $resultado->assertForbidden();
    }


    public function test_planner_cannot_create_employee_cost_rate(): void
    {
        // Crear un usuario planificador.
        $rolePlanner = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            [
                'display_name' => 'Planificador',
                'description' => 'Gestión de planificación',
            ]
        );

        $planner = User::create([
            'name' => 'Planificador Tarifas',
            'email' => 'planner-tarifas@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rolePlanner->id);

        // Intentar asignar una tarifa de 99,99 €/hora.
        $resultado = Livewire::actingAs($planner)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->set('coste_hora', 99.99)
            ->set('tarifa_fecha_inicio', '2026-12-01')
            ->call('guardarTarifa');

        // La tarifa NO debe haberse creado.
        $this->assertDatabaseMissing('tarifas_coste', [
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 99.99,
            'fecha_inicio' => '2026-12-01',
        ]);

        // El planificador debe recibir un 403.
        $resultado->assertForbidden();
    }

    public function test_planner_cannot_delete_employee_cost_rate(): void
    {
        // Crear una tarifa existente de 25 €/hora.
        $tarifa = \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 25.00,
            'fecha_inicio' => '2026-01-01',
        ]);

        // Crear usuario planificador.
        $rolePlanner = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            [
                'display_name' => 'Planificador',
                'description' => 'Gestión de planificación',
            ]
        );

        $planner = User::create([
            'name' => 'Planificador Tarifas',
            'email' => 'planner-delete-tarifa@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rolePlanner->id);

        // Intentar eliminar la tarifa desde Livewire.
        $resultado = Livewire::actingAs($planner)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->call('eliminarTarifa', $tarifa->id);




        // El planificador debe recibir un 403.
        $resultado->assertForbidden();
    }
    public function test_admin_can_create_employee_cost_rate(): void
    {
        // El administrador crea una tarifa válida.
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->set('coste_hora', 25.50)
            ->set('tarifa_fecha_inicio', '2026-12-01')
            ->call('guardarTarifa')
            ->assertHasNoErrors();

        // Verificar que la tarifa se ha guardado.
        $this->assertDatabaseHas('tarifas_coste', [
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 25.50,
            'fecha_inicio' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_hr_can_create_employee_cost_rate(): void
    {
        // Crear rol RR. HH.
        $roleHR = Role::firstOrCreate(
            ['name' => Role::RRHH],
            [
                'display_name' => 'Recursos Humanos',
                'description' => 'Gestión de personal',
            ]
        );

        // Crear usuario de RR. HH.
        $rrhh = User::create([
            'name' => 'Responsable RRHH',
            'email' => 'rrhh-tarifa@mita.es',
            'password' => bcrypt('password123'),
        ]);

        $rrhh->roles()->attach($roleHR->id);

        // RR. HH. crea una tarifa.
        Livewire::actingAs($rrhh)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->set('coste_hora', 22.50)
            ->set('tarifa_fecha_inicio', '2026-12-01')
            ->call('guardarTarifa')
            ->assertHasNoErrors();

        // Comprobar que se ha guardado correctamente.
        $this->assertDatabaseHas('tarifas_coste', [
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 22.50,
            'fecha_inicio' => '2026-12-01 00:00:00',
        ]);
    }

    public function test_admin_can_create_employee_with_dni(): void
    {
        // Utilizamos la categoría existente en los datos de prueba.
        $categoriaId = $this->empleado->categoria_id;

        // El administrador crea un empleado nuevo.
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
            ->call('abrirModalCrear')
            ->set('nombre', 'David')
            ->set('apellidos', 'García')
            ->set('dni_nie', 'X6791299E')
            ->set('categoria_id', $categoriaId)
            ->call('guardar')
            ->assertHasNoErrors();

        // Comprobar que realmente existe en la base de datos.
        $this->assertDatabaseHas('empleados', [
            'nombre' => 'David',
            'apellidos' => 'García',
            'dni_nie' => 'X6791299E',
            'categoria_id' => $categoriaId,
            'activo' => true,
        ]);
    }

    public function test_cannot_create_employee_with_duplicate_dni(): void
    {
        // El empleado existente ya tiene este DNI.
        $this->empleado->update([
            'dni_nie' => 'X1234567L',
        ]);

        $totalAntes = \App\Models\Empleado::count();

        // Intentar crear otro empleado con el mismo DNI.
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Rrhh\EmployeeIndex::class)
            ->call('abrirModalCrear')
            ->set('nombre', 'Pedro')
            ->set('apellidos', 'Prueba')
            ->set('dni_nie', 'X1234567L')
            ->set('categoria_id', $this->empleado->categoria_id)
            ->call('guardar')
            ->assertHasErrors(['dni_nie' => 'unique']);

        // No debe haberse creado otro empleado.
        $this->assertEquals(
            $totalAntes,
            \App\Models\Empleado::count()
        );
    }

    public function test_cannot_create_overlapping_employee_cost_rates(): void
    {
        // Pablo ya tiene una tarifa de 15 €/hora.
        \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-10-30',
            'fecha_fin' => null,
        ]);

        $totalAntes = \App\Models\TarifaCoste::count();

        // Intentar crear otra tarifa para el mismo período.
        $resultado = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Economics\CostRateManager::class)
            ->call('abrirModalCrear')
            ->set('tipoAsignacion', 'empleado')
            ->set('empleado_id', $this->empleado->id)
            ->set('categoria_id', null)
            ->set('coste_hora', 20)
            ->set('fecha_inicio', '2026-10-30')
            ->set('fecha_fin', '')
            ->call('guardar');

        // No debe crearse otra tarifa solapada.
        $this->assertEquals(
            $totalAntes,
            \App\Models\TarifaCoste::count()
        );

        // Debe informar del conflicto de fechas.
        $resultado->assertHasErrors(['fecha_inicio']);
    }

    public function test_employee_file_cannot_create_overlapping_cost_rates(): void
    {
        // Tarifa existente: 15 €/hora desde octubre.
        \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-10-30',
            'fecha_fin' => null,
        ]);

        $totalAntes = \App\Models\TarifaCoste::count();

        // Intentar crear otra tarifa desde la ficha de RR. HH.
        $resultado = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Rrhh\EmployeeShow::class, [
                'id' => $this->empleado->id,
            ])
            ->set('coste_hora', 20)
            ->set('tarifa_fecha_inicio', '2026-11-01')
            ->set('tarifa_fecha_fin', '')
            ->call('guardarTarifa');

        // No debe guardarse ninguna tarifa nueva.
        $this->assertEquals(
            $totalAntes,
            \App\Models\TarifaCoste::count()
        );

        // Debe informar del solapamiento.
        $resultado->assertHasErrors(['tarifa_fecha_inicio']);
    }

    public function test_cannot_edit_cost_rate_to_overlap_another(): void
    {
        // Primera tarifa: enero a junio.
        $primera = \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        // Segunda tarifa: julio en adelante.
        $segunda = \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 20,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => null,
        ]);

        // Intentar modificar la segunda tarifa
        // para que comience el mismo día que termina la primera.
        $resultado = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Economics\CostRateManager::class)
            ->call('abrirModalEditar', $segunda->id)
            ->set('fecha_inicio', '2026-06-30')
            ->call('guardar');

        // Debe detectar el solapamiento.
        $resultado->assertHasErrors(['fecha_inicio']);

        // La segunda tarifa debe conservar su fecha original.
        $this->assertDatabaseHas('tarifas_coste', [
            'id' => $segunda->id,
            'coste_hora' => 20,
            'fecha_inicio' => '2026-07-01 00:00:00',
        ]);

        // La primera tarifa también debe permanecer intacta.
        $this->assertDatabaseHas('tarifas_coste', [
            'id' => $primera->id,
            'coste_hora' => 15,
            'fecha_fin' => '2026-06-30 00:00:00',
        ]);
    }

    public function test_employee_cannot_create_cost_rate_from_economics(): void
    {
        $totalAntes = \App\Models\TarifaCoste::count();

        // Un empleado normal intenta crear una tarifa.
        $resultado = Livewire::actingAs($this->employeeUser)
            ->test(\App\Livewire\Economics\CostRateManager::class)
            ->call('abrirModalCrear')
            ->set('tipoAsignacion', 'empleado')
            ->set('empleado_id', $this->empleado->id)
            ->set('categoria_id', null)
            ->set('coste_hora', 41.50)
            ->set('fecha_inicio', '2028-01-01')
            ->set('fecha_fin', '')
            ->call('guardar');

        // No debe crearse ninguna tarifa.
        $this->assertSame(
            $totalAntes,
            \App\Models\TarifaCoste::count()
        );

        // El servidor debe denegar la operación.
        $resultado->assertForbidden();
    }

    public function test_employee_cannot_delete_cost_rate_from_economics(): void
    {
        // Crear una tarifa existente.
        $tarifa = \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 25.00,
            'fecha_inicio' => '2028-01-01',
            'fecha_fin' => null,
        ]);

        // Un empleado intenta eliminarla directamente.
        $resultado = Livewire::actingAs($this->employeeUser)
            ->test(\App\Livewire\Economics\CostRateManager::class)
            ->set('tarifaToDeleteId', $tarifa->id)
            ->call('ejecutarEliminar');

        // La tarifa debe permanecer en la base de datos.
        $this->assertDatabaseHas('tarifas_coste', [
            'id' => $tarifa->id,
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 25.00,
        ]);

        // Laravel debe denegar la operación.
        $resultado->assertForbidden();
    }

    public function test_cannot_create_overlapping_category_cost_rates(): void
    {
        // Crear tarifa general de categoría.
        \App\Models\TarifaCoste::create([
            'categoria_id' => $this->empleado->categoria_id,
            'empleado_id' => null,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        $totalAntes = \App\Models\TarifaCoste::count();

        // Intentar crear otra tarifa para la misma categoría.
        $resultado = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Economics\CostRateManager::class)
            ->call('abrirModalCrear')
            ->set('tipoAsignacion', 'categoria')
            ->set('categoria_id', $this->empleado->categoria_id)
            ->set('empleado_id', null)
            ->set('coste_hora', 25)
            ->set('fecha_inicio', '2026-07-01')
            ->set('fecha_fin', '')
            ->call('guardar');

        // No debe crearse una tarifa contradictoria.
        $this->assertEquals(
            $totalAntes,
            \App\Models\TarifaCoste::count()
        );

        // Debe aparecer un error de vigencia.
        $resultado->assertHasErrors(['fecha_inicio']);
    }

    public function test_rrhh_can_create_temporary_rate_override(): void
    {
        // Preparar el escenario aislado de prueba.
        \App\Models\TarifaCoste::where(
            'empleado_id',
            $this->empleado->id
        )->delete();

        // Tarifa personalizada habitual.
        \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        // Simular al administrador utilizando Livewire.
        \Livewire\Livewire::actingAs($this->admin)
            ->test(
                \App\Livewire\Rrhh\EmployeeShow::class,
                ['id' => $this->empleado->id]
            )
            ->call('abrirModalTarifa')
            ->set('modoTarifa', 'excepcion')
            ->set('coste_hora', 22)
            ->set('tarifa_fecha_inicio', '2026-07-01')
            ->set('tarifa_fecha_fin', '2026-08-31')
            ->call('guardarTarifa')
            ->assertHasNoErrors()
            ->assertSet('modalTarifaOpen', false);

        // Verificar que existen los tres períodos.
        $this->assertEquals(
            3,
            \App\Models\TarifaCoste::where(
                'empleado_id',
                $this->empleado->id
            )->count()
        );

        // Verificar la tarifa aplicable en cada período.
        $this->assertEquals(
            15,
            $this->empleado->tarifaEnFecha('2026-06-30')
        );

        $this->assertEquals(
            22,
            $this->empleado->tarifaEnFecha('2026-07-15')
        );

        $this->assertEquals(
            15,
            $this->empleado->tarifaEnFecha('2026-09-01')
        );
    }

    public function test_employee_cannot_create_temporary_rate_override(): void
    {
        // Crear una tarifa personalizada inicial.
        $tarifa = \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        // Un empleado normal intenta crear una excepción temporal.
        Livewire::actingAs($this->employeeUser)
            ->test(
                \App\Livewire\Rrhh\EmployeeShow::class,
                ['id' => $this->empleado->id]
            )
            ->set('modoTarifa', 'excepcion')
            ->set('coste_hora', 22)
            ->set('tarifa_fecha_inicio', '2026-07-01')
            ->set('tarifa_fecha_fin', '2026-08-31')
            ->call('guardarTarifa')
            ->assertForbidden();

        // La tarifa original debe permanecer intacta.
        $tarifa->refresh();

        $this->assertEquals(15, $tarifa->coste_hora);
        $this->assertNull($tarifa->fecha_fin);

        // No debe haberse creado ninguna tarifa adicional.
        $this->assertEquals(
            1,
            \App\Models\TarifaCoste::where(
                'empleado_id',
                $this->empleado->id
            )->count()
        );
    }

    public function test_temporary_rate_rejects_invalid_dates_without_changes(): void
    {
        // Tarifa original indefinida.
        $tarifa = \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        // Intentar crear una excepción con fechas incorrectas.
        Livewire::actingAs($this->admin)
            ->test(
                \App\Livewire\Rrhh\EmployeeShow::class,
                ['id' => $this->empleado->id]
            )
            ->call('abrirModalTarifa')
            ->set('modoTarifa', 'excepcion')
            ->set('coste_hora', 22)
            ->set('tarifa_fecha_inicio', '2026-07-01')
            ->set('tarifa_fecha_fin', '2026-06-30')
            ->call('guardarTarifa')
            ->assertHasErrors(['tarifa_fecha_fin']);

        // Verificar que la tarifa original sigue intacta.
        $tarifa->refresh();

        $this->assertEquals(15, $tarifa->coste_hora);
        $this->assertNull($tarifa->fecha_fin);

        // No debe existir ninguna tarifa adicional.
        $this->assertEquals(
            1,
            \App\Models\TarifaCoste::where(
                'empleado_id',
                $this->empleado->id
            )->count()
        );
    }
    public function test_temporary_rate_preserves_observations(): void
    {
        \App\Models\TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(
                \App\Livewire\Rrhh\EmployeeShow::class,
                ['id' => $this->empleado->id]
            )
            ->call('abrirModalTarifa')
            ->set('modoTarifa', 'excepcion')
            ->set('coste_hora', 22)
            ->set('tarifa_fecha_inicio', '2026-07-01')
            ->set('tarifa_fecha_fin', '2026-08-31')
            ->set(
                'observaciones_tarifa',
                'Sustitución temporal de coordinador'
            )
            ->call('guardarTarifa')
            ->assertHasNoErrors();

        $excepcion = \App\Models\TarifaCoste::query()
            ->where('empleado_id', $this->empleado->id)
            ->where('coste_hora', 22)
            ->firstOrFail();

        $this->assertSame(
            'Sustitución temporal de coordinador',
            $excepcion->observaciones
        );
    }
    public function test_crear_usuario_no_compara_id_con_cadena_vacia(): void
    {
        $consultas = [];

        \Illuminate\Support\Facades\DB::listen(
            function (\Illuminate\Database\Events\QueryExecuted $query) use (&$consultas) {
                $consultas[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                ];
            }
        );

        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalCrear')
            ->set('name', 'Usuario de prueba')
            ->set('email', 'testCuadrante@mita.com')
            ->set('password', 'secret1234')
            ->set('password_confirmation', 'secret1234')
            ->set('selectedRoles', [$this->roleEmployee->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $consultasUnicidad = collect($consultas)->filter(
            fn($consulta) =>
            str_contains(strtolower($consulta['sql']), 'from "users"')
                && str_contains(strtolower($consulta['sql']), 'count(')
        );

        $this->assertNotEmpty(
            $consultasUnicidad,
            'No se encontró la consulta de unicidad del correo.'
        );

        foreach ($consultasUnicidad as $consulta) {
            $this->assertNotContains(
                '',
                $consulta['bindings'],
                'La validación no debe comparar users.id con una cadena vacía.'
            );
        }
    }
    public function test_admin_puede_editar_usuario_conservando_email(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $this->employeeUser->id)
            ->set('name', 'Usuario Actualizado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $this->employeeUser->id,
            'name' => 'Usuario Actualizado',
            'email' => $this->employeeUser->email,
        ]);
    }
    public function test_admin_crea_usuario_con_email_normalizado_a_minusculas(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalCrear')
            ->set('name', 'Usuario Test')
            ->set('email', 'TestRol@MiTa.COM')
            ->set('password', 'secret1234')
            ->set('password_confirmation', 'secret1234')
            ->set('selectedRoles', [$this->roleEmployee->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Usuario Test',
            'email' => 'testrol@mita.com',
        ]);
    }
    public function test_no_permite_email_duplicado_con_distintas_mayusculas(): void
    {
        // Simulamos un usuario antiguo con el correo sin normalizar.
        User::factory()->create([
            'email' => 'TestRol@MiTa.COM',
        ]);

        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalCrear')
            ->set('name', 'Usuario Duplicado')
            ->set('email', 'testrol@mita.com')
            ->set('password', 'secret1234')
            ->set('password_confirmation', 'secret1234')
            ->set('selectedRoles', [$this->roleEmployee->id])
            ->call('guardar')
            ->assertHasErrors(['email']);

        // El usuario duplicado no debe crearse.
        $this->assertDatabaseMissing('users', [
            'name' => 'Usuario Duplicado',
        ]);
    }
    public function test_usuario_puede_iniciar_sesion_con_email_en_mayusculas(): void
    {
        $this->employeeUser->update([
            'email' => 'empleado@mita.com',
        ]);

        $consultas = [];

        \Illuminate\Support\Facades\DB::listen(
            function (\Illuminate\Database\Events\QueryExecuted $query) use (&$consultas) {
                $consultas[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                ];
            }
        );

        $this->post('/login', [
            'email' => 'EMPLEADO@MITA.COM',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($this->employeeUser);

        $consultasUsuarios = collect($consultas)->filter(
            fn($consulta) =>
            str_contains(strtolower($consulta['sql']), 'from "users"')
                && str_contains(strtolower($consulta['sql']), 'lower(email)')
        );

        $this->assertNotEmpty(
            $consultasUsuarios,
            'No se encontró ninguna consulta de autenticación por correo.'
        );

        foreach ($consultasUsuarios as $consulta) {
            $this->assertNotContains(
                'EMPLEADO@MITA.COM',
                $consulta['bindings'],
                'La autenticación no debe buscar el correo sin normalizar.'
            );
        }
    }
    public function test_usuario_antiguo_con_email_en_mayusculas_puede_iniciar_sesion(): void
    {
        // Simulamos una cuenta creada antes de normalizar los correos.
        $this->employeeUser->update([
            'email' => 'Martin@MiTa.COM',
        ]);

        $this->post('/login', [
            'email' => 'martin@mita.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($this->employeeUser);
    }
    public function test_admin_no_puede_quitarse_su_propio_rol_administrador(): void
    {
        $this->assertTrue($this->admin->isAdmin());

        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $this->admin->id)
            ->set('selectedRoles', [$this->roleEmployee->id])
            ->call('guardar');

        // El administrador debe conservar su rol.
        $this->admin->refresh()->load('roles');

        $this->assertTrue(
            $this->admin->isAdmin(),
            'MiTa permitió que un administrador se quitara su propio rol.'
        );
    }
    public function test_no_se_puede_eliminar_al_ultimo_administrador_directamente(): void
    {
        // El administrador de la prueba debe ser el único administrador.
        $this->assertEquals(
            1,
            User::whereHas(
                'roles',
                fn($query) => $query->where('name', Role::ADMIN)
            )->count()
        );

        // Simulamos una petición Livewire que evita confirmarEliminar().
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->set('userToDeleteId', $this->admin->id)
            ->call('eliminar');

        // El administrador debe continuar existiendo y conservar sus roles.
        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
        ]);

        $this->admin->refresh()->load('roles');

        $this->assertTrue(
            $this->admin->isAdmin(),
            'MiTa permitió eliminar al último administrador.'
        );
    }
    public function test_superadministrador_tiene_acceso_a_todos_los_modulos(): void
    {
        $superadminRole = Role::firstOrCreate(
            ['name' => 'superadmin'],
            [
                'display_name' => 'Superadministrador',
                'description' => 'Control total de MiTa',
            ]
        );

        $superadmin = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        // Le asignamos exclusivamente el rol superadmin.
        $superadmin->roles()->sync([$superadminRole->id]);

        $superadmin->refresh()->load('roles');

        // Debe heredar todas las capacidades administrativas.
        $this->assertTrue($superadmin->isAdmin());
        $this->assertTrue($superadmin->isRrhh());
        $this->assertTrue($superadmin->isPlanner());
        $this->assertTrue($superadmin->isAccountant());
    }
    public function test_admin_no_puede_asignarse_rol_superadmin(): void
    {
        $superadminRole = Role::firstOrCreate(
            ['name' => Role::SUPERADMIN],
            ['display_name' => 'Superadministrador']
        );

        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $this->admin->id)
            ->set('selectedRoles', [
                $this->admin->roles->first()->id,
                $superadminRole->id,
            ])
            ->call('guardar');

        $this->admin->refresh()->load('roles');

        $this->assertFalse(
            $this->admin->isSuperAdmin(),
            'Un administrador consiguió elevar sus privilegios a Superadministrador.'
        );
    }
    public function test_admin_no_puede_modificar_cuenta_superadmin(): void
    {
        $superadminRole = Role::firstOrCreate(
            ['name' => Role::SUPERADMIN],
            ['display_name' => 'Superadministrador']
        );

        $superadmin = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $superadmin->roles()->sync([$superadminRole->id]);

        // Un administrador normal intenta modificar la cuenta propietaria.
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $superadmin->id)
            ->set('selectedRoles', [$this->roleEmployee->id])
            ->set('name', 'Administrador intruso')
            ->set('email', 'intruso@mita.com')
            ->set('password', 'nuevaclave123')
            ->set('password_confirmation', 'nuevaclave123')
            ->call('guardar');

        $superadmin->refresh()->load('roles');

        // Ningún dato de la cuenta propietaria debe modificarse.
        $this->assertSame('Nekonapster', $superadmin->name);
        $this->assertSame('neko@mita.com', $superadmin->email);
        $this->assertTrue($superadmin->isSuperAdmin());
    }

    public function test_admin_no_puede_eliminar_cuenta_superadmin(): void
    {
        $superadminRole = Role::firstOrCreate(
            ['name' => Role::SUPERADMIN],
            ['display_name' => 'Superadministrador']
        );

        $superadmin = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $superadmin->roles()->sync([$superadminRole->id]);

        // Un administrador normal intenta eliminar directamente
        // la cuenta del propietario.
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->set('userToDeleteId', $superadmin->id)
            ->call('eliminar');

        // La cuenta debe seguir existiendo y conservar sus privilegios.
        $this->assertDatabaseHas('users', [
            'id' => $superadmin->id,
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $superadmin->refresh()->load('roles');

        $this->assertTrue(
            $superadmin->isSuperAdmin(),
            'MiTa permitió eliminar al Superadministrador.'
        );
    }

    public function test_admin_no_puede_desactivar_cuenta_superadmin(): void
    {
        $superadminRole = Role::firstOrCreate(
            ['name' => Role::SUPERADMIN],
            ['display_name' => 'Superadministrador']
        );

        $superadmin = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $superadmin->roles()->sync([$superadminRole->id]);

        // Un administrador normal intenta desactivar al propietario.
        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('cambiarEstadoCuenta', $superadmin->id);

        // Nekonapster debe seguir teniendo acceso.
        $this->assertDatabaseHas('users', [
            'id' => $superadmin->id,
            'activo' => true,
        ]);

        $superadmin->refresh()->load('roles');

        $this->assertTrue(
            $superadmin->isSuperAdmin(),
            'MiTa permitió modificar los privilegios del Superadministrador.'
        );
    }

    public function test_superadmin_no_puede_quitarse_su_propio_rol(): void
    {
        $superadminRole = Role::firstOrCreate(
            ['name' => Role::SUPERADMIN],
            ['display_name' => 'Superadministrador']
        );

        $superadmin = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $superadmin->roles()->sync([$superadminRole->id]);

        // Nekonapster intenta retirarse su propio rol.
        Livewire::actingAs($superadmin)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $superadmin->id)
            ->set('selectedRoles', [
                Role::where('name', Role::ADMIN)->firstOrFail()->id,
            ])
            ->call('guardar');

        $superadmin->refresh()->load('roles');

        $this->assertTrue(
            $superadmin->isSuperAdmin(),
            'MiTa permitió que el Superadministrador perdiera su propio rol.'
        );
    }
    public function test_no_se_puede_asignar_superadmin_a_dos_usuarios(): void
    {
        $rolSuperadmin = Role::firstOrCreate(
            ['name' => Role::SUPERADMIN],
            ['display_name' => 'Superadministrador']
        );

        // Primer y único Superadministrador.
        $nekonapster = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
        ]);

        $nekonapster->roles()->attach($rolSuperadmin->id);

        // Intentamos crear otro Superadministrador.
        $otroUsuario = User::factory()->create();

        try {
            $otroUsuario->roles()->attach($rolSuperadmin->id);
        } catch (\Illuminate\Database\QueryException $e) {
            // La base de datos ha rechazado la segunda asignación.
        }

        // Debe existir exactamente un Superadministrador.
        $this->assertEquals(
            1,
            \Illuminate\Support\Facades\DB::table('role_user')
                ->where('role_id', $rolSuperadmin->id)
                ->count(),
            'MiTa permitió asignar el rol Superadministrador a dos usuarios.'
        );
    }
    public function test_no_se_puede_retirar_rol_al_unico_superadmin(): void
    {
        $rolSuperadmin = Role::where('name', Role::SUPERADMIN)->firstOrFail();

        $nekonapster = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $nekonapster->roles()->attach($rolSuperadmin->id);

        Livewire::actingAs($nekonapster)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $nekonapster->id)
            ->set('selectedRoles', [
                Role::where('name', Role::ADMIN)->firstOrFail()->id,
            ])
            ->call('guardar');

        $nekonapster->refresh()->load('roles');

        $this->assertTrue(
            $nekonapster->isSuperAdmin(),
            'Se permitió retirar el rol del único Superadministrador.'
        );
    }
    public function test_admin_no_puede_abrir_confirmacion_eliminar_superadmin(): void
    {
        $rolSuperadmin = Role::where('name', Role::SUPERADMIN)->firstOrFail();

        $nekonapster = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        $nekonapster->roles()->attach($rolSuperadmin->id);
        // Debe existir más de un administrador normal.
        $otroAdmin = User::factory()->create([
            'activo' => true,
        ]);

        $rolAdmin = Role::where('name', Role::ADMIN)->firstOrFail();

        $otroAdmin->roles()->attach($rolAdmin->id);

        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('confirmarEliminar', $nekonapster->id)
            ->assertSet('modalDeleteOpen', false);

        $this->assertDatabaseHas('users', [
            'id' => $nekonapster->id,
            'activo' => true,
        ]);
    }

    public function test_superadmin_puede_editar_sus_datos_sin_rol_admin(): void
    {
        $rolSuperadmin = Role::where('name', Role::SUPERADMIN)->firstOrFail();

        $nekonapster = User::factory()->create([
            'name' => 'Nekonapster',
            'email' => 'neko@mita.com',
            'activo' => true,
        ]);

        // Solamente tiene el rol Superadministrador.
        $nekonapster->roles()->sync([$rolSuperadmin->id]);

        Livewire::actingAs($nekonapster)
            ->test(UserRoleManager::class)
            ->call('abrirModalEditar', $nekonapster->id)
            ->set('name', 'Nekonapster MiTa')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $nekonapster->id,
            'name' => 'Nekonapster MiTa',
            'email' => 'neko@mita.com',
        ]);

        $nekonapster->refresh()->load('roles');

        $this->assertTrue($nekonapster->isSuperAdmin());
        $this->assertFalse($nekonapster->hasRole(Role::ADMIN));
    }
}
