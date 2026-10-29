<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Ausencia;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Services\ShiftAssignmentValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftAssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $catSocorrista;
    private Categoria $catMonitor;
    private Empleado $empleado;
    private Turno $turno;
    private ShiftAssignmentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ShiftAssignmentValidator();

        $this->catSocorrista = Categoria::create(['nombre' => 'Socorrista', 'codigo' => 'SOC']);
        $this->catMonitor = Categoria::create(['nombre' => 'Monitor', 'codigo' => 'MON']);

        $this->empleado = Empleado::create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'categoria_id' => $this->catSocorrista->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $this->empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40.00,
        ]);

        $cliente = Cliente::create(['razon_social' => 'Cliente Test']);
        $inst = Instalacion::create(['cliente_id' => $cliente->id, 'nombre' => 'Piscina Municipal']);
        $act = Actividad::create(['nombre' => 'Socorrismo']);
        $proy = Proyecto::create(['cliente_id' => $cliente->id, 'nombre' => 'Proyecto Test', 'fecha_inicio' => '2026-01-01']);

        $this->turno = Turno::create([
            'proyecto_id' => $proy->id,
            'instalacion_id' => $inst->id,
            'actividad_id' => $act->id,
            'categoria_id' => $this->catSocorrista->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00', 'Europe/Madrid'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);
    }

    public function test_valid_assignment_passes(): void
    {
        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertTrue($res['valido']);
        $this->assertEmpty($res['errores']);
    }

    public function test_inactive_employee_fails(): void
    {
        $this->empleado->update(['activo' => false]);

        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('inactivo', $res['errores'][0]);
    }

    public function test_missing_or_expired_contract_fails(): void
    {
        // Contrato que expira antes del turno
        $this->empleado->contratos()->delete();
        Contrato::create([
            'empleado_id' => $this->empleado->id,
            'tipo_contrato' => 'Temporal',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-05-31', // El turno es el 15 de junio
            'jornada_semanal_horas' => 40.00,
        ]);

        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('contrato laboral vigente', $res['errores'][0]);
    }

    public function test_incompatible_category_fails(): void
    {
        $this->empleado->update(['categoria_id' => $this->catMonitor->id]);

        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('categoría profesional', $res['errores'][0]);
    }

    public function test_approved_absence_conflict_fails(): void
    {
        Ausencia::create([
            'empleado_id' => $this->empleado->id,
            'tipo' => Ausencia::TIPO_VACACIONES,
            'fecha_hora_inicio' => Carbon::parse('2026-06-15 08:00:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-06-15 15:00:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('ausencia aprobada incompatible', $res['errores'][0]);
    }

    public function test_overlapping_shift_fails(): void
    {
        // Crear otro turno solapado en la misma fecha y asignar al empleado
        $otroTurno = Turno::create([
            'proyecto_id' => $this->turno->proyecto_id,
            'instalacion_id' => $this->turno->instalacion_id,
            'actividad_id' => $this->turno->actividad_id,
            'categoria_id' => $this->catSocorrista->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 14:00:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-15 20:00:00', 'Europe/Madrid'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        Asignacion::create([
            'turno_id' => $otroTurno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('Solapamiento con otro turno asignado', $res['errores'][0]);
    }

    public function test_full_capacity_fails(): void
    {
        $otroEmpleado = Empleado::create([
            'nombre' => 'Ana',
            'apellidos' => 'López',
            'categoria_id' => $this->catSocorrista->id,
            'activo' => true,
        ]);

        Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $otroEmpleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        $res = $this->validator->validarAsignacion($this->turno, $this->empleado);
        $this->assertFalse($res['valido']);
        $this->assertStringContainsString('No quedan plazas disponibles', $res['errores'][0]);
    }

    public function test_approving_absence_marks_existing_assignment_with_conflict(): void
    {
        $asig = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        $this->assertFalse($asig->tiene_conflicto);

        // Se aprueba una ausencia sobrevenida
        $ausencia = Ausencia::create([
            'empleado_id' => $this->empleado->id,
            'tipo' => Ausencia::TIPO_BAJA,
            'fecha_hora_inicio' => Carbon::parse('2026-06-15 00:00:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-06-15 23:59:59', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        $afectados = $this->validator->marcarConflictosPorAusencia($ausencia);

        $this->assertEquals(1, $afectados);
        $asig->refresh();
        $this->assertTrue($asig->tiene_conflicto);
        $this->assertStringContainsString('Ausencia aprobada sobrevenida', $asig->motivo_conflicto);
        $this->assertEquals('con_conflicto', $this->turno->estadoCobertura());
    }

    public function test_cancelling_shift_preserves_assignments_without_counting_hours(): void
    {
        // Crear segundo empleado
        $otroEmpleado = Empleado::create([
            'nombre' => 'Ana',
            'apellidos' => 'Prueba',
            'categoria_id' => $this->catSocorrista->id,
            'activo' => true,
        ]);

        // Asignar dos empleados al turno
        foreach ([$this->empleado, $otroEmpleado] as $empleado) {
            Asignacion::create([
                'turno_id' => $this->turno->id,
                'empleado_id' => $empleado->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        $servicio = new \App\Services\EconomicCalculationService();

        // Antes de cancelar: 2 empleados × 8 horas
        $informeAntes = $servicio->calcularInformeProyecto(
            $this->turno->proyecto
        );

        $this->assertEquals(16, $informeAntes['horas_previstas']);

        // Crear planificador autorizado
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $planner = \App\Models\User::create([
            'name' => 'Planificador',
            'email' => 'planner_cancelacion@test.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rol->id);

        // Cancelar el turno
        \Livewire\Livewire::actingAs($planner)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->call(
                'cambiarEstado',
                $this->turno->id,
                Turno::ESTADO_CANCELADO
            );

        // El turno queda cancelado
        $this->assertDatabaseHas('turnos', [
            'id' => $this->turno->id,
            'estado' => Turno::ESTADO_CANCELADO,
        ]);

        // Se conservan las dos asignaciones
        $this->assertEquals(
            2,
            Asignacion::where('turno_id', $this->turno->id)->count()
        );

        // Ambas asignaciones quedan canceladas
        $this->assertEquals(
            2,
            Asignacion::where('turno_id', $this->turno->id)
                ->where('estado', Asignacion::ESTADO_CANCELADO)
                ->count()
        );

        // Comprobar los cálculos económicos
        $informe = $servicio->calcularInformeProyecto(
            $this->turno->proyecto
        );

        $this->assertEquals(0, $informe['horas_previstas']);
        $this->assertEquals(0, $informe['horas_realizadas']);
    }

    public function test_cannot_cancel_shift_with_registered_hours(): void
    {
        // Crear una asignación
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Registrar 4 horas realmente trabajadas
        $registro = \App\Models\RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => '2026-06-15 10:00:00',
            'fin_real' => '2026-06-15 14:00:00',
            'descanso_minutos' => 0,
            'horas_computables' => 4,
            'estado' => \App\Models\RegistroHora::ESTADO_VALIDADO,
        ]);

        // Crear un planificador autorizado
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $planner = \App\Models\User::create([
            'name' => 'Planificador Test',
            'email' => 'planner_horas@test.es',
            'password' => bcrypt('password123'),
        ]);

        $planner->roles()->attach($rol->id);

        // Intentar cancelar el turno
        \Livewire\Livewire::actingAs($planner)
            ->test(\App\Livewire\Roster\ShiftManager::class)
            ->call('cambiarEstado', $this->turno->id, Turno::ESTADO_CANCELADO)
            ->assertHasErrors(['nuevoEstado']);

        // El turno debe continuar publicado
        $this->assertDatabaseHas('turnos', [
            'id' => $this->turno->id,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // La asignación debe permanecer confirmada
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacion->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Las 4 horas deben conservarse
        $this->assertDatabaseHas('registros_horas', [
            'id' => $registro->id,
            'horas_computables' => 4,
            'estado' => \App\Models\RegistroHora::ESTADO_VALIDADO,
        ]);
    }

    public function test_employee_cannot_register_hours_for_another_employee(): void
    {
        // Crear otro empleado
        $otroEmpleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'Prueba',
            'categoria_id' => $this->catSocorrista->id,
            'activo' => true,
        ]);

        // Asignar el turno a Pedro
        $asignacionAjena = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $otroEmpleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Crear usuario vinculado a otro trabajador
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = \App\Models\User::create([
            'name' => 'Juan Usuario',
            'email' => 'juan_seguridad@test.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);

        $usuario->roles()->attach($rol->id);

        // Juan intenta registrar horas en la asignación de Pedro
        $resultado = \Livewire\Livewire::actingAs($usuario)
            ->test(\App\Livewire\Portal\EmployeePortal::class)
            ->set('asignacionSeleccionadaId', $asignacionAjena->id)
            ->set('inicio_real', '2026-06-15T10:00')
            ->set('fin_real', '2026-06-15T18:00')
            ->set('descanso_minutos', 0)
            ->call('guardarImputacion');

        // No debe crearse ningún registro ajeno
        $this->assertDatabaseMissing('registros_horas', [
            'asignacion_id' => $asignacionAjena->id,
        ]);

        // Laravel debe rechazar la operación
        $resultado->assertForbidden();
    }

    public function test_employee_cannot_open_another_employee_hours(): void
    {
        // Crear empleado Pedro
        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'Prueba',
            'categoria_id' => $this->catSocorrista->id,
            'activo' => true,
        ]);

        // Crear asignación de Pedro
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Crear usuario Juan
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $juan = \App\Models\User::create([
            'name' => 'Juan',
            'email' => 'juan_privacidad@test.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);

        $juan->roles()->attach($rol->id);

        // Juan intenta abrir la imputación de Pedro
        \Livewire\Livewire::actingAs($juan)
            ->test(\App\Livewire\Portal\EmployeePortal::class)
            ->call('abrirModalImputar', $asignacion->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_modify_validated_hours(): void
    {
        // Crear asignación del propio empleado
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Crear registro de 4 horas ya validadas
        $registro = \App\Models\RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => '2026-06-15 10:00:00',
            'fin_real' => '2026-06-15 14:00:00',
            'descanso_minutos' => 0,
            'horas_computables' => 4,
            'estado' => \App\Models\RegistroHora::ESTADO_VALIDADO,
        ]);

        // Crear cuenta vinculada al trabajador
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = \App\Models\User::create([
            'name' => 'Juan',
            'email' => 'juan_validado@test.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);

        $usuario->roles()->attach($rol->id);

        // Juan intenta cambiar sus 4 horas validadas por 8
        $resultado = \Livewire\Livewire::actingAs($usuario)
            ->test(\App\Livewire\Portal\EmployeePortal::class)
            ->set('asignacionSeleccionadaId', $asignacion->id)
            ->set('inicio_real', '2026-06-15T10:00')
            ->set('fin_real', '2026-06-15T18:00')
            ->set('descanso_minutos', 0)
            ->call('guardarImputacion');

        // Las 4 horas aprobadas deben conservarse
        $this->assertDatabaseHas('registros_horas', [
            'id' => $registro->id,
            'horas_computables' => 4,
            'estado' => \App\Models\RegistroHora::ESTADO_VALIDADO,
        ]);

        // La modificación debe estar prohibida
        $resultado->assertForbidden();
    }

    public function test_employee_can_modify_pending_hours(): void
    {
        // Crear asignación del empleado
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Crear registro pendiente de 4 horas
        $registro = \App\Models\RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => '2026-06-15 10:00:00',
            'fin_real' => '2026-06-15 14:00:00',
            'descanso_minutos' => 0,
            'horas_computables' => 4,
            'estado' => \App\Models\RegistroHora::ESTADO_PENDIENTE,
        ]);

        // Crear usuario vinculado al empleado
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = \App\Models\User::create([
            'name' => 'Juan',
            'email' => 'juan_pendiente@test.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);

        $usuario->roles()->attach($rol->id);

        // Modificar sus horas de 4 a 8
        \Livewire\Livewire::actingAs($usuario)
            ->test(\App\Livewire\Portal\EmployeePortal::class)
            ->set('asignacionSeleccionadaId', $asignacion->id)
            ->set('inicio_real', '2026-06-15T10:00')
            ->set('fin_real', '2026-06-15T18:00')
            ->set('descanso_minutos', 0)
            ->call('guardarImputacion')
            ->assertHasNoErrors();

        // Comprobar que se actualizaron las horas
        $this->assertDatabaseHas('registros_horas', [
            'id' => $registro->id,
            'horas_computables' => 8,
            'estado' => \App\Models\RegistroHora::ESTADO_PENDIENTE,
        ]);

        // Comprobar que no se creó otro registro
        $this->assertDatabaseCount('registros_horas', 1);
    }

    public function test_employee_cannot_register_hours_on_cancelled_shift(): void
    {
        // Cancelar el turno
        $this->turno->update([
            'estado' => Turno::ESTADO_CANCELADO,
        ]);

        // Crear una asignación que todavía figura como confirmada
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Crear usuario vinculado al trabajador
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = \App\Models\User::create([
            'name' => 'Juan',
            'email' => 'juan_cancelado@test.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);

        $usuario->roles()->attach($rol->id);

        // Intentar registrar 8 horas en el turno cancelado
        $resultado = \Livewire\Livewire::actingAs($usuario)
            ->test(\App\Livewire\Portal\EmployeePortal::class)
            ->set('asignacionSeleccionadaId', $asignacion->id)
            ->set('inicio_real', '2026-06-15T10:00')
            ->set('fin_real', '2026-06-15T18:00')
            ->set('descanso_minutos', 0)
            ->call('guardarImputacion');

        // No deben crearse registros de horas
        $this->assertDatabaseMissing('registros_horas', [
            'asignacion_id' => $asignacion->id,
        ]);

        // La operación debe rechazarse
        $resultado->assertForbidden();
    }

    public function test_employee_cannot_register_hours_on_cancelled_assignment(): void
    {
        // Cancelar el turno
        $this->turno->update([
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Crear una asignación que todavía figura como confirmada
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        // Crear usuario vinculado al trabajador
        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = \App\Models\User::create([
            'name' => 'Juan',
            'email' => 'juan_cancelado@test.es',
            'password' => bcrypt('password123'),
            'employee_id' => $this->empleado->id,
        ]);

        $usuario->roles()->attach($rol->id);

        // Intentar registrar 8 horas en el turno cancelado
        $resultado = \Livewire\Livewire::actingAs($usuario)
            ->test(\App\Livewire\Portal\EmployeePortal::class)
            ->set('asignacionSeleccionadaId', $asignacion->id)
            ->set('inicio_real', '2026-06-15T10:00')
            ->set('fin_real', '2026-06-15T18:00')
            ->set('descanso_minutos', 0)
            ->call('guardarImputacion');

        // No deben crearse registros de horas
        $this->assertDatabaseMissing('registros_horas', [
            'asignacion_id' => $asignacion->id,
        ]);

        // La operación debe rechazarse
        $resultado->assertForbidden();
    }

    public function test_employee_cannot_open_cancelled_assignment(): void
{
    // El turno está publicado
    $this->turno->update([
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    // Pero la asignación está cancelada
    $asignacion = Asignacion::create([
        'turno_id' => $this->turno->id,
        'empleado_id' => $this->empleado->id,
        'estado' => Asignacion::ESTADO_CANCELADO,
    ]);

    // Crear usuario vinculado al empleado
    $rol = \App\Models\Role::firstOrCreate(
        ['name' => \App\Models\Role::EMPLOYEE],
        ['display_name' => 'Empleado']
    );

    $usuario = \App\Models\User::create([
        'name' => 'Juan',
        'email' => 'juan_modal_cancelado@test.es',
        'password' => bcrypt('password123'),
        'employee_id' => $this->empleado->id,
    ]);

    $usuario->roles()->attach($rol->id);

    // Intentar abrir la asignación cancelada
    \Livewire\Livewire::actingAs($usuario)
        ->test(\App\Livewire\Portal\EmployeePortal::class)
        ->call('abrirModalImputar', $asignacion->id)
        ->assertForbidden();

    // La asignación permanece como historial
    $this->assertDatabaseHas('asignaciones', [
        'id' => $asignacion->id,
        'estado' => Asignacion::ESTADO_CANCELADO,
    ]);
}
}
