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
}
