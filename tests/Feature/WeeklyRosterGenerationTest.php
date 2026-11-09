<?php

namespace Tests\Feature;

use App\Livewire\Roster\WeeklyRoster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\Role;
use App\Services\AutomaticScheduleService;
use App\Models\Instalacion;
use App\Models\Categoria;
use App\Models\Actividad;
use App\Models\Turno;
use Carbon\Carbon;
use App\Models\Asignacion;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\RegistroHora;

class WeeklyRosterGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_generar_sin_seleccionar_proyecto(): void
    {
        $usuario = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $usuario->roles()->attach($rol->id);

        $this->actingAs($usuario);

        Livewire::test(WeeklyRoster::class)
            ->set('proyectoId', null)
            ->call('generarAutomaticamente')
            ->assertHasErrors(['proyectoId']);
    }
    public function test_empleado_no_puede_generar_cuadrantes_automaticamente(): void
    {
        $empleado = User::factory()->create();

        $rol = Role::create([
            'name' => Role::EMPLOYEE,
            'display_name' => 'Empleado',
        ]);

        $empleado->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Test',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($empleado);

        Livewire::test(WeeklyRoster::class)
            ->set('proyectoId', $proyecto->id)
            ->call('generarAutomaticamente')
            ->assertForbidden();
    }
    public function test_administrador_puede_ejecutar_generador_desde_livewire(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Test',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        // Simulamos el servicio para verificar que Livewire lo llama
        // con el proyecto y las fechas correctas.
        $servicio = \Mockery::mock(AutomaticScheduleService::class);

        $servicio->shouldReceive('generar')
            ->once()
            ->withArgs(function ($proyectoRecibido, $desde, $hasta) use ($proyecto) {
                return $proyectoRecibido instanceof Proyecto
                    && $proyectoRecibido->id === $proyecto->id
                    && $desde === '2026-06-15'
                    && $hasta === '2026-06-21';
            });

        $this->app->instance(AutomaticScheduleService::class, $servicio);

        Livewire::test(WeeklyRoster::class)
            ->set('proyectoId', $proyecto->id)
            ->set('currentDate', '2026-06-17')
            ->set('viewMode', 'weekly')
            ->call('generarAutomaticamente')
            ->assertHasNoErrors();
    }
    public function test_administrador_ve_boton_generacion_automatica(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $this->actingAs($administrador);

        Livewire::test(WeeklyRoster::class)
            ->assertSee('Generar automáticamente');
    }
    public function test_empleado_no_ve_boton_generacion_automatica(): void
    {
        $empleado = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado->roles()->attach($rol->id);

        $this->actingAs($empleado);

        Livewire::test(WeeklyRoster::class)
            ->assertDontSee('Generar automáticamente');
    }
    public function test_cambiar_cliente_limpia_proyecto_e_instalacion(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $clienteA = Cliente::create([
            'razon_social' => 'Ayuntamiento A',
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'Ayuntamiento B',
        ]);

        $proyectoA = Proyecto::create([
            'cliente_id' => $clienteA->id,
            'nombre' => 'Campaña A',
            'fecha_inicio' => '2026-01-01',
        ]);

        $instalacionA = Instalacion::create([
            'cliente_id' => $clienteA->id,
            'nombre' => 'Piscina A',
        ]);

        $this->actingAs($administrador);

        Livewire::test(WeeklyRoster::class)
            ->set('clienteId', $clienteA->id)
            ->set('proyectoId', $proyectoA->id)
            ->set('instalacionId', $instalacionA->id)

            // Cambiar al cliente B.
            ->set('clienteId', $clienteB->id)

            // No deben conservarse selecciones incompatibles.
            ->assertSet('proyectoId', null)
            ->assertSet('instalacionId', null);
    }

    public function test_solo_muestra_proyectos_del_cliente_seleccionado(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $clienteA = Cliente::create([
            'razon_social' => 'Ayuntamiento A',
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'Ayuntamiento B',
        ]);

        Proyecto::create([
            'cliente_id' => $clienteA->id,
            'nombre' => 'Campaña Socorrismo A',
            'fecha_inicio' => '2026-01-01',
        ]);

        Proyecto::create([
            'cliente_id' => $clienteB->id,
            'nombre' => 'Campaña Socorrismo B',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        $componente = Livewire::test(WeeklyRoster::class)
            ->set('clienteId', $clienteA->id);

        $proyectos = $componente->viewData('proyectos');

        $this->assertCount(1, $proyectos);

        $this->assertSame(
            'Campaña Socorrismo A',
            $proyectos->first()->nombre
        );
    }
    public function test_solo_muestra_instalaciones_del_cliente_seleccionado(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $clienteA = Cliente::create([
            'razon_social' => 'Ayuntamiento A',
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'Ayuntamiento B',
        ]);

        Instalacion::create([
            'cliente_id' => $clienteA->id,
            'nombre' => 'Piscina A',
            'activo' => true,
        ]);

        Instalacion::create([
            'cliente_id' => $clienteB->id,
            'nombre' => 'Piscina B',
            'activo' => true,
        ]);

        $this->actingAs($administrador);

        $componente = Livewire::test(WeeklyRoster::class)
            ->set('clienteId', $clienteA->id);

        $instalaciones = $componente->viewData('instalaciones');

        $this->assertCount(1, $instalaciones);

        $this->assertSame(
            'Piscina A',
            $instalaciones->first()->nombre
        );
    }
    public function test_solo_muestra_instalaciones_asociadas_al_proyecto(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacionA = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Cavaleri',
            'activo' => true,
        ]);

        Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal B',
            'activo' => true,
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Asociamos exclusivamente Cavaleri al proyecto.
        $proyecto->instalaciones()->attach($instalacionA->id);

        $this->actingAs($administrador);

        $componente = Livewire::test(WeeklyRoster::class)
            ->set('clienteId', $cliente->id)
            ->set('proyectoId', $proyecto->id);

        $instalaciones = $componente->viewData('instalaciones');

        $this->assertCount(1, $instalaciones);

        $this->assertSame(
            $instalacionA->id,
            $instalaciones->first()->id
        );
    }
    public function test_no_genera_si_proyecto_no_pertenece_al_cliente(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $clienteA = Cliente::create([
            'razon_social' => 'Ayuntamiento A',
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'Ayuntamiento B',
        ]);

        $proyectoB = Proyecto::create([
            'cliente_id' => $clienteB->id,
            'nombre' => 'Campaña B',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        // Simulamos el servicio para detectar cualquier ejecución indebida.
        $servicio = \Mockery::mock(
            \App\Services\AutomaticScheduleService::class
        );

        $servicio->shouldNotReceive('generar');

        $this->app->instance(
            \App\Services\AutomaticScheduleService::class,
            $servicio
        );

        Livewire::test(WeeklyRoster::class)
            ->set('clienteId', $clienteA->id)
            ->set('proyectoId', $proyectoB->id)
            ->call('generarAutomaticamente')
            ->assertHasErrors(['proyectoId']);
    }
    public function test_no_genera_si_instalacion_no_pertenece_al_proyecto(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $cavaleri = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
            'activo' => true,
        ]);

        $otraPiscina = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Otra Piscina',
            'activo' => true,
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Únicamente Cavaleri pertenece al proyecto.
        $proyecto->instalaciones()->attach($cavaleri->id);

        $this->actingAs($administrador);

        // El servicio no debe ejecutarse.
        $servicio = \Mockery::mock(
            \App\Services\AutomaticScheduleService::class
        );

        $servicio->shouldNotReceive('generar');

        $this->app->instance(
            \App\Services\AutomaticScheduleService::class,
            $servicio
        );

        Livewire::test(WeeklyRoster::class)
            ->set('clienteId', $cliente->id)
            ->set('proyectoId', $proyecto->id)
            ->set('instalacionId', $otraPiscina->id)
            ->call('generarAutomaticamente')
            ->assertHasErrors(['instalacionId']);
    }
    public function test_generador_recibe_instalacion_seleccionada(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
            'activo' => true,
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $proyecto->instalaciones()->attach($instalacion->id);

        $this->actingAs($administrador);

        $servicio = \Mockery::mock(
            \App\Services\AutomaticScheduleService::class
        );

        $servicio->shouldReceive('generar')
            ->once()
            ->withArgs(function (
                $proyectoRecibido,
                $desde,
                $hasta,
                $instalacionId
            ) use ($proyecto, $instalacion) {
                return $proyectoRecibido->id === $proyecto->id
                    && $desde === '2026-06-15'
                    && $hasta === '2026-06-21'
                    && $instalacionId === $instalacion->id;
            });

        $this->app->instance(
            \App\Services\AutomaticScheduleService::class,
            $servicio
        );

        Livewire::test(WeeklyRoster::class)
            ->set('currentDate', '2026-06-17')
            ->set('clienteId', $cliente->id)
            ->set('proyectoId', $proyecto->id)
            ->set('instalacionId', $instalacion->id)
            ->call('generarAutomaticamente');
    }

    public function test_vista_mensual_permite_abrir_modal_de_turnos(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
            'activo' => true,
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $this->actingAs($administrador);

        Livewire::test(WeeklyRoster::class)
            ->set('viewMode', 'monthly')
            ->set('currentDate', '2026-06-15')
            ->assertSeeHtml('wire:click="abrirModalTurno(' . $turno->id . ')"');
    }

    public function test_empleado_no_puede_asignar_personal_manualmente(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        Livewire::actingAs($usuario)
            ->test(WeeklyRoster::class)
            ->call('asignarEmpleado')
            ->assertForbidden();

        $this->assertDatabaseCount('asignaciones', 0);
    }
    public function test_empleado_no_puede_desasignar_personal(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        Livewire::actingAs($usuario)
            ->test(WeeklyRoster::class)
            ->call('desasignarEmpleado', 999999)
            ->assertForbidden();

        $this->assertDatabaseCount('asignaciones', 0);
    }
    public function test_administrador_puede_asignar_personal_manualmente(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
            'activo' => true,
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        Livewire::actingAs($administrador)
            ->test(WeeklyRoster::class)
            ->call('abrirModalTurno', $turno->id)
            ->set('nuevoEmpleadoId', $empleado->id)
            ->call('asignarEmpleado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }
    public function test_administrador_puede_desasignar_personal(): void
    {
        $administrador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $administrador->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
            'activo' => true,
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $asignacion = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        Livewire::actingAs($administrador)
            ->test(WeeklyRoster::class)
            ->call('abrirModalTurno', $turno->id)
            ->call('desasignarEmpleado', $asignacion->id)
            ->assertHasNoErrors();

        // La asignación debe conservarse como cancelada.
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacion->id,
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        // El turno sigue publicado.
        $this->assertDatabaseHas('turnos', [
            'id' => $turno->id,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // La plaza vuelve a estar disponible.
        $this->assertSame(1, $turno->fresh()->plazasDisponibles());
    }
    public function test_planificador_puede_asignar_personal(): void
    {
        $planificador = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $planificador->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
            'activo' => true,
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        Livewire::actingAs($planificador)
            ->test(WeeklyRoster::class)
            ->call('abrirModalTurno', $turno->id)
            ->set('nuevoEmpleadoId', $empleado->id)
            ->call('asignarEmpleado')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }

    public function test_no_puede_desasignar_empleado_con_horas_pendientes(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Test',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $asignacion = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        $registro = RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => Carbon::parse('2026-06-15 08:00:00'),
            'fin_real' => Carbon::parse('2026-06-15 12:00:00'),
            'descanso_minutos' => 0,
            'horas_computables' => 4,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
        ]);

        Livewire::actingAs($admin)
            ->test(WeeklyRoster::class)
            ->call('abrirModalTurno', $turno->id)
            ->call('desasignarEmpleado', $asignacion->id)
            ->assertSet('erroresValidacion', [
                'No se puede desasignar al empleado porque existen horas registradas. Deben revisarse previamente.',
            ]);

        // La asignación sigue confirmada.
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacion->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Las horas registradas permanecen intactas.
        $this->assertDatabaseHas('registros_horas', [
            'id' => $registro->id,
            'asignacion_id' => $asignacion->id,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
        ]);

        // La plaza continúa ocupada.
        $this->assertSame(0, $turno->fresh()->plazasDisponibles());
    }
}
