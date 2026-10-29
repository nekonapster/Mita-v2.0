<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\NecesidadServicio;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Services\ShiftGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftGenerationTest extends TestCase
{
    use RefreshDatabase;

    private NecesidadServicio $necesidad;

    protected function setUp(): void
    {
        parent::setUp();

        $cliente = Cliente::create(['razon_social' => 'Cliente Test']);
        $instalacion = Instalacion::create(['cliente_id' => $cliente->id, 'nombre' => 'Instalacion Test']);
        $cat = Categoria::create(['nombre' => 'Socorrista', 'codigo' => 'SOC']);
        $act = Actividad::create(['nombre' => 'Socorrismo']);
        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $this->necesidad = NecesidadServicio::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $act->id,
            'categoria_id' => $cat->id,
            'fecha_inicio_vigencia' => '2026-06-01',
            'fecha_fin_vigencia' => '2026-06-30',
            'dias_semana' => [1, 2, 3, 4, 5], // Lunes a Viernes
            'hora_inicio' => '10:00:00',
            'hora_fin' => '14:00:00',
            'cruza_medianoche' => false,
            'plazas_necesarias' => 2,
        ]);
    }

    public function test_generates_shifts_only_on_matching_weekdays_within_date_range(): void
    {
        $service = new ShiftGenerationService();

        // 2026-06-01 es Lunes, 2026-06-07 es Domingo (5 días laborables de lunes a viernes)
        $res = $service->generarTurnos($this->necesidad, '2026-06-01', '2026-06-07');

        $this->assertEquals(5, $res['generados']);
        $this->assertEquals(0, $res['omitidos']);
        $this->assertEquals(5, Turno::count());

        $primerTurno = Turno::first();
        $this->assertEquals('2026-06-01', $primerTurno->fecha->toDateString());
        $this->assertEquals(2, $primerTurno->plazas_necesarias);
        $this->assertEquals(4.0, $primerTurno->duracionHoras());
    }

    public function test_avoids_generating_duplicate_shifts(): void
    {
        $service = new ShiftGenerationService();

        // Primera generación: 5 turnos creados
        $res1 = $service->generarTurnos($this->necesidad, '2026-06-01', '2026-06-07');
        $this->assertEquals(5, $res1['generados']);

        // Segunda generación sobre el mismo rango: 0 creados, 5 omitidos
        $res2 = $service->generarTurnos($this->necesidad, '2026-06-01', '2026-06-07');
        $this->assertEquals(0, $res2['generados']);
        $this->assertEquals(5, $res2['omitidos']);
        $this->assertEquals(5, Turno::count());
    }

    public function test_handles_midnight_crossing_shifts_correctly(): void
    {
        $this->necesidad->update([
            'hora_inicio' => '22:00:00',
            'hora_fin' => '06:00:00',
            'cruza_medianoche' => true,
            'dias_semana' => [5], // Solo viernes
        ]);

        $service = new ShiftGenerationService();
        // 2026-06-05 es Viernes
        $res = $service->generarTurnos($this->necesidad, '2026-06-05', '2026-06-05');

        $this->assertEquals(1, $res['generados']);
        $turno = Turno::first();

        $this->assertEquals('2026-06-05', $turno->fecha->toDateString());
        $this->assertEquals('2026-06-05 22:00:00', Carbon::parse($turno->hora_inicio)->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-06-06 06:00:00', Carbon::parse($turno->hora_fin)->format('Y-m-d H:i:s'));
        $this->assertEquals(8.0, $turno->duracionHoras());
    }

    public function test_planner_can_publish_shifts(): void
    {
        // Crear el rol de planificador
        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::PLANNER,
            'display_name' => 'Planificador',
        ]);

        // Crear usuario planificador
        $planner = \App\Models\User::create([
            'name' => 'Planificador Test',
            'email' => 'planner@test.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rol->id);

        // 1. Comprobar que tiene el rol.
        $this->assertTrue(
            $planner->fresh()->hasRole(\App\Models\Role::PLANNER),
            'ERROR: El usuario no tiene el rol planner.'
        );

        // 2. Comprobar que Laravel encuentra la política.
        $policy = \Illuminate\Support\Facades\Gate::getPolicyFor(Turno::class);

        $this->assertInstanceOf(
            \App\Policies\TurnoPolicy::class,
            $policy,
            'ERROR: Laravel no encuentra TurnoPolicy.'
        );

        // 3. Comprobar que existe el método publish.
        $this->assertTrue(
            method_exists($policy, 'publish'),
            'ERROR: TurnoPolicy no tiene el método publish.'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Gate::forUser($planner)
                ->allows('publish', Turno::class),
            'El planificador no tiene permiso para publicar turnos.'
        );
        // Generar turnos en borrador
        $servicio = new ShiftGenerationService();

        $servicio->generarTurnos(
            $this->necesidad,
            '2026-06-01',
            '2026-06-07'
        );

        // Comprobar que existen 5 borradores
        $this->assertSame(
            5,
            Turno::where('estado', Turno::ESTADO_BORRADOR)->count()
        );

        // Publicar como planificador
        $componente = \Livewire\Livewire::actingAs($planner)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->assertStatus(200)
            ->set('fechaDesde', '2026-06-01')
            ->assertSet('fechaDesde', '2026-06-01')
            ->set('fechaHasta', '2026-06-07')
            ->assertSet('fechaHasta', '2026-06-07')
            ->call('publicarBorradores')
            ->assertStatus(200)
            ->assertSet(
                'feedbackMessage',
                'Se han publicado 5 turnos en el cuadrante.'
            );



        // Comprobar que los 5 turnos están publicados
        $this->assertSame(
            5,
            Turno::where('estado', Turno::ESTADO_PUBLICADO)->count()
        );

        // No deben quedar borradores
        $this->assertSame(
            0,
            Turno::where('estado', Turno::ESTADO_BORRADOR)->count()
        );
    }

    public function test_employee_cannot_change_shift_state(): void
    {
        // Crear usuario empleado
        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::EMPLOYEE,
            'display_name' => 'Empleado',
        ]);

        $empleado = \App\Models\User::create([
            'name' => 'Empleado Test',
            'email' => 'empleado@test.es',
            'password' => bcrypt('password123'),
        ]);

        $empleado->roles()->attach($rol->id);

        // Generar turnos
        $servicio = new ShiftGenerationService();

        $servicio->generarTurnos(
            $this->necesidad,
            '2026-06-01',
            '2026-06-07'
        );

        $turno = Turno::firstOrFail();

        // Intentar publicar un turno sin autorización
        $resultado = \Livewire\Livewire::actingAs($empleado)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->call('cambiarEstado', $turno->id, Turno::ESTADO_PUBLICADO);

        // El turno debe continuar en borrador
        $this->assertDatabaseHas('turnos', [
            'id' => $turno->id,
            'estado' => Turno::ESTADO_BORRADOR,
        ]);

        // Laravel debe rechazar la operación
        $resultado->assertForbidden();
    }

    public function test_planner_can_change_shift_state(): void
    {
        // Crear usuario planificador
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $planner = \App\Models\User::create([
            'name' => 'Planificador Test',
            'email' => 'planner_estado@test.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rol->id);

        // Generar turnos
        $servicio = new ShiftGenerationService();

        $servicio->generarTurnos(
            $this->necesidad,
            '2026-06-01',
            '2026-06-07'
        );

        $turno = Turno::firstOrFail();

        // Verificar estado inicial
        $this->assertEquals(
            Turno::ESTADO_BORRADOR,
            $turno->estado
        );

        // Modificar como planificador
        \Livewire\Livewire::actingAs($planner)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->call('cambiarEstado', $turno->id, Turno::ESTADO_PUBLICADO)
            ->assertStatus(200);

        // Verificar que el estado cambió realmente
        $this->assertDatabaseHas('turnos', [
            'id' => $turno->id,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);
    }

    public function test_planner_cannot_set_invalid_shift_state(): void
    {
        // Crear planificador autorizado
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $planner = \App\Models\User::create([
            'name' => 'Planificador Test',
            'email' => 'planner_invalido@test.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rol->id);

        // Generar turnos
        $servicio = new ShiftGenerationService();

        $servicio->generarTurnos(
            $this->necesidad,
            '2026-06-01',
            '2026-06-07'
        );

        $turno = Turno::firstOrFail();

        // Intentar introducir un estado inventado
        \Livewire\Livewire::actingAs($planner)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->call('cambiarEstado', $turno->id, 'pendiente123')
            ->assertHasErrors(['nuevoEstado']);

        // El estado original debe conservarse
        $this->assertDatabaseHas('turnos', [
            'id' => $turno->id,
            'estado' => Turno::ESTADO_BORRADOR,
        ]);
    }
}
