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
    public function test_empleado_no_puede_acceder_a_gestion_de_clientes(): void
    {
        $this->actingAs($this->employeeUser)
            ->get(route('servicios.clientes'))
            ->assertForbidden();
    }
    public function test_empleado_no_puede_crear_clientes_mediante_livewire(): void
    {
        $this->actingAs($this->employeeUser);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->assertForbidden();

        $this->assertDatabaseMissing('clientes', [
            'razon_social' => 'Cliente no autorizado',
        ]);
    }

    public function test_contable_puede_consultar_clientes(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('servicios.clientes'))
            ->assertOk();
    }
    public function test_contable_no_puede_crear_clientes_mediante_livewire(): void
    {
        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('razon_social', 'Cliente creado por contabilidad')
            ->call('guardarCliente')
            ->assertForbidden();

        $this->assertDatabaseMissing('clientes', [
            'razon_social' => 'Cliente creado por contabilidad',
        ]);
    }
    public function test_contable_no_puede_crear_instalaciones_mediante_livewire(): void
    {
        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('instalacion_nombre', 'Instalación no autorizada')
            ->call('guardarInstalacion')
            ->assertForbidden();

        $this->assertDatabaseMissing('instalaciones', [
            'nombre' => 'Instalación no autorizada',
        ]);
    }

    public function test_contable_no_puede_eliminar_clientes_mediante_livewire(): void
    {
        $cliente = \App\Models\Cliente::create([
            'razon_social' => 'Cliente protegido',
            'activo' => true,
        ]);

        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('clienteToDeleteId', $cliente->id)
            ->call('eliminarCliente')
            ->assertForbidden();

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'razon_social' => 'Cliente protegido',
            'activo' => true,
        ]);
    }
    public function test_contable_no_puede_eliminar_instalaciones_mediante_livewire(): void
    {
        $cliente = \App\Models\Cliente::create([
            'razon_social' => 'Cliente de prueba',
            'activo' => true,
        ]);

        $instalacion = \App\Models\Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Instalación protegida',
            'activo' => true,
        ]);

        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('instalacionToDeleteId', $instalacion->id)
            ->call('eliminarInstalacion')
            ->assertForbidden();

        $this->assertDatabaseHas('instalaciones', [
            'id' => $instalacion->id,
            'nombre' => 'Instalación protegida',
            'activo' => true,
        ]);
    }

    public function test_contable_no_puede_abrir_formulario_de_clientes(): void
    {
        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->call('abrirModalCliente')
            ->assertForbidden();
    }
    public function test_contable_no_puede_abrir_formulario_de_instalaciones(): void
    {
        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->call('abrirModalInstalacion', 1)
            ->assertForbidden();
    }
    public function test_no_elimina_cliente_con_instalaciones_aunque_se_manipule_livewire(): void
    {
        $cliente = \App\Models\Cliente::create([
            'razon_social' => 'Cliente con historial',
            'activo' => true,
        ]);

        \App\Models\Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Instalación histórica',
            'activo' => true,
        ]);

        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('clienteToDeleteId', $cliente->id)
            ->set('clienteTieneRegistros', false)
            ->call('eliminarCliente');

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'activo' => false,
        ]);

        $this->assertDatabaseHas('instalaciones', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Instalación histórica',
        ]);
    }
    public function test_no_elimina_instalacion_con_necesidades_aunque_se_manipule_livewire(): void
    {
        $cliente = \App\Models\Cliente::create([
            'razon_social' => 'Cliente histórico',
        ]);

        $instalacion = \App\Models\Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Instalación histórica',
            'activo' => true,
        ]);

        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $actividad = \App\Models\Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = \App\Models\Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto histórico',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $necesidad = \App\Models\NecesidadServicio::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha_inicio_vigencia' => '2026-06-01',
            'fecha_fin_vigencia' => '2026-06-30',
            'dias_semana' => [1, 2, 3, 4, 5],
            'hora_inicio' => '10:00:00',
            'hora_fin' => '14:00:00',
            'plazas_necesarias' => 1,
        ]);

        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('instalacionToDeleteId', $instalacion->id)
            ->set('instalacionTieneRegistros', false)
            ->call('eliminarInstalacion');

        $this->assertDatabaseHas('instalaciones', [
            'id' => $instalacion->id,
            'activo' => false,
        ]);

        $this->assertDatabaseHas('necesidades_servicio', [
            'id' => $necesidad->id,
            'instalacion_id' => $instalacion->id,
        ]);
    }

    public function test_contable_puede_consultar_clientes_sin_ver_acciones_administrativas(): void
    {
        $cliente = \App\Models\Cliente::create([
            'razon_social' => 'Cliente de consulta',
            'activo' => true,
        ]);

        \App\Models\Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Instalación de consulta',
            'activo' => true,
        ]);

        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->assertSee('Cliente de consulta')
            ->assertSee('Instalación de consulta')
            ->assertDontSee('Nuevo Cliente')
            ->assertDontSee('Añadir Instalación')
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar');
    }

    public function test_contable_no_puede_mostrar_modal_cliente_manipulando_livewire(): void
    {
        $this->actingAs($this->accountant);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->set('modalClienteOpen', true)
            ->assertDontSee('Razón Social o Nombre');
    }

    public function test_contable_no_puede_mostrar_otros_modales_manipulando_livewire(): void
    {
        $this->actingAs($this->accountant);

        $modales = [
            'modalInstalacionOpen' => 'Añadir Nueva Instalación',
            'modalDeleteClienteOpen' => '¿Eliminar cliente?',
            'modalDeleteInstalacionOpen' => '¿Eliminar instalación?',
        ];

        foreach ($modales as $propiedad => $texto) {
            \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
                ->set($propiedad, true)
                ->assertDontSee($texto);
        }
    }
    public function test_planificador_puede_gestionar_clientes_mediante_livewire(): void
    {
        $this->actingAs($this->planner);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->assertSee('Nuevo Cliente')
            ->call('abrirModalCliente')
            ->assertSee('Razón Social o Nombre')
            ->set('razon_social', 'Cliente creado por planificador')
            ->call('guardarCliente')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'razon_social' => 'Cliente creado por planificador',
        ]);
    }

    public function test_administrador_puede_gestionar_clientes_mediante_livewire(): void
    {
        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Livewire\Services\ClientIndex::class)
            ->assertSee('Nuevo Cliente')
            ->call('abrirModalCliente')
            ->assertSee('Razón Social o Nombre')
            ->set('razon_social', 'Cliente creado por administrador')
            ->call('guardarCliente')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'razon_social' => 'Cliente creado por administrador',
        ]);
    }
}
