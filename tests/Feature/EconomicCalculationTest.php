<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\MovimientoEconomico;
use App\Models\Proyecto;
use App\Models\RegistroHora;
use App\Models\TarifaCoste;
use App\Models\Turno;
use App\Services\EconomicCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EconomicCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private Empleado $empleado;
    private Turno $turno;
    private EconomicCalculationService $econService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->econService = new EconomicCalculationService();

        $cat = Categoria::create(['nombre' => 'Socorrista', 'codigo' => 'SOC']);
        $this->empleado = Empleado::create([
            'nombre' => 'Carlos',
            'apellidos' => 'García',
            'categoria_id' => $cat->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $this->empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'jornada_semanal_horas' => 40.00,
        ]);

        // Tarifa por hora de 15.00 €
        TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 15.00,
            'fecha_inicio' => '2026-01-01',
        ]);

        $cliente = Cliente::create(['razon_social' => 'Ayuntamiento']);
        $inst = Instalacion::create(['cliente_id' => $cliente->id, 'nombre' => 'Piscina Municipal']);
        $act = Actividad::create(['nombre' => 'Socorrismo']);

        $this->proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña 2026',
            'fecha_inicio' => '2026-06-01',
        ]);

        // Turno de 8 horas: 10:00 a 18:00
        $this->turno = Turno::create([
            'proyecto_id' => $this->proyecto->id,
            'instalacion_id' => $inst->id,
            'actividad_id' => $act->id,
            'categoria_id' => $cat->id,
            'fecha' => '2026-06-10',
            'hora_inicio' => Carbon::parse('2026-06-10 10:00:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-10 18:00:00', 'Europe/Madrid'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $asig = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Registro de horas validadas: 7.50 horas computables
        RegistroHora::create([
            'asignacion_id' => $asig->id,
            'inicio_real' => Carbon::parse('2026-06-10 10:00:00', 'Europe/Madrid'),
            'fin_real' => Carbon::parse('2026-06-10 18:00:00', 'Europe/Madrid'),
            'descanso_minutos' => 30,
            'horas_computables' => 7.50,
            'estado' => RegistroHora::ESTADO_VALIDADO,
        ]);

        // Movimientos económicos: 1000 € ingreso, 100 € gasto en materiales
        MovimientoEconomico::create([
            'proyecto_id' => $this->proyecto->id,
            'tipo' => MovimientoEconomico::TIPO_INGRESO,
            'fecha' => '2026-06-10',
            'concepto' => 'Factura cliente',
            'importe' => 1000.00,
        ]);

        MovimientoEconomico::create([
            'proyecto_id' => $this->proyecto->id,
            'tipo' => MovimientoEconomico::TIPO_GASTO,
            'fecha' => '2026-06-10',
            'concepto' => 'Material botiquín',
            'importe' => 100.00,
        ]);
    }

    public function test_calculates_projected_and_actual_costs_accurately(): void
    {
        $informe = $this->econService->calcularInformeProyecto($this->proyecto);

        // Turno de 8 horas x 15 €/h = 120.00 €
        $this->assertEquals(8.00, $informe['horas_previstas']);
        $this->assertEquals(120.00, $informe['coste_personal_previsto']);
        $this->assertFalse($informe['coste_previsto_incompleto']);

        // Horas reales validadas: 7.50h x 15 €/h = 112.50 €
        $this->assertEquals(7.50, $informe['horas_realizadas']);
        $this->assertEquals(112.50, $informe['coste_personal_real']);
        $this->assertFalse($informe['coste_real_incompleto']);

        // Ingresos: 1000 €, Gastos adicionales: 100 €
        $this->assertEquals(1000.00, $informe['ingresos_totales']);
        $this->assertEquals(100.00, $informe['gastos_adicionales']);

        // Resultado estimado = 1000 - 112.50 - 100 = 787.50 €
        $this->assertEquals(787.50, $informe['resultado_estimado']);
        $this->assertNotEmpty($informe['desglose_explicativo']);
    }

    public function test_flags_incomplete_cost_when_rate_is_missing(): void
    {
        // Borramos la tarifa
        TarifaCoste::query()->delete();

        $informe = $this->econService->calcularInformeProyecto($this->proyecto);

        $this->assertTrue($informe['coste_previsto_incompleto']);
        $this->assertTrue($informe['coste_real_incompleto']);
        $this->assertEquals(0.00, $informe['coste_personal_real']);
    }

    public function test_cancelled_assignment_does_not_count_hours(): void
    {
        // Crear una segunda empleada
        $ana = Empleado::create([
            'nombre' => 'Ana',
            'apellidos' => 'Prueba',
            'categoria_id' => $this->empleado->categoria_id,
            'activo' => true,
        ]);

        // Asignación cancelada, conservada como historial
        $asignacion = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $ana->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        $informe = $this->econService
            ->calcularInformeProyecto($this->proyecto);

        // La asignación cancelada sigue existiendo
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacion->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        // Solo computan las horas de Carlos
        $this->assertEquals(8, $informe['horas_previstas']);
        $this->assertEquals(7.5, $informe['horas_realizadas']);

        // Los costes tampoco deben incrementarse
        $this->assertEquals(120, $informe['coste_personal_previsto']);
        $this->assertEquals(112.50, $informe['coste_personal_real']);
    }

    public function test_employee_hours_summary_calculates_individual_hours(): void
    {
        $servicio = new \App\Services\EmployeeHoursSummaryService();

        $resumen = $servicio->calcular(
            $this->empleado,
            '2026-06-01',
            '2026-06-30'
        );

        // Carlos tiene 8 horas planificadas
        $this->assertEquals(
            8,
            $resumen['horas_planificadas']
        );

        // Carlos ha trabajado 7,5 horas validadas
        $this->assertEquals(
            7.5,
            $resumen['horas_realizadas']
        );

        // No tiene horas pendientes
        $this->assertEquals(
            0,
            $resumen['horas_pendientes']
        );

        // No tiene horas canceladas
        $this->assertEquals(
            0,
            $resumen['horas_canceladas']
        );
    }

    public function test_employee_hours_are_calculated_independently(): void
    {
        // Permitir dos trabajadores en el turno.
        $this->turno->update(['plazas_necesarias' => 2]);

        // Crear a Ana.
        $ana = Empleado::create([
            'nombre' => 'Ana',
            'apellidos' => 'Prueba',
            'categoria_id' => $this->empleado->categoria_id,
            'activo' => true,
        ]);

        // Asignar a Ana al mismo turno que Carlos.
        $asignacionAna = Asignacion::create([
            'turno_id' => $this->turno->id,
            'empleado_id' => $ana->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Ana registra 6 horas pendientes de validar.
        RegistroHora::create([
            'asignacion_id' => $asignacionAna->id,
            'inicio_real' => '2026-06-10 10:00:00',
            'fin_real' => '2026-06-10 16:00:00',
            'descanso_minutos' => 0,
            'horas_computables' => 6,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
        ]);

        $servicio = new \App\Services\EmployeeHoursSummaryService();

        // Calcular cada empleado por separado.
        $carlos = $servicio->calcular(
            $this->empleado,
            '2026-06-01',
            '2026-06-30'
        );

        $resumenAna = $servicio->calcular(
            $ana,
            '2026-06-01',
            '2026-06-30'
        );

        // Carlos: 8 previstas, 7,5 validadas.
        $this->assertEquals(8, $carlos['horas_planificadas']);
        $this->assertEquals(7.5, $carlos['horas_realizadas']);
        $this->assertEquals(0, $carlos['horas_pendientes']);

        // Ana: 8 previstas, 6 pendientes, ninguna validada.
        $this->assertEquals(8, $resumenAna['horas_planificadas']);
        $this->assertEquals(0, $resumenAna['horas_realizadas']);
        $this->assertEquals(6, $resumenAna['horas_pendientes']);
    }

    public function test_employee_hours_summary_respects_date_range(): void
    {
        // Crear un turno de 6 horas en julio
        $turnoJulio = Turno::create([
            'proyecto_id' => $this->turno->proyecto_id,
            'instalacion_id' => $this->turno->instalacion_id,
            'actividad_id' => $this->turno->actividad_id,
            'categoria_id' => $this->turno->categoria_id,
            'fecha' => '2026-07-01',
            'hora_inicio' => Carbon::parse('2026-07-01 10:00:00'),
            'hora_fin' => Carbon::parse('2026-07-01 16:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Asignar el turno a Carlos
        $asignacion = Asignacion::create([
            'turno_id' => $turnoJulio->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Registrar 5,5 horas validadas
        RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => '2026-07-01 10:00:00',
            'fin_real' => '2026-07-01 16:00:00',
            'descanso_minutos' => 30,
            'horas_computables' => 5.5,
            'estado' => RegistroHora::ESTADO_VALIDADO,
        ]);

        $servicio = new \App\Services\EmployeeHoursSummaryService();

        // Consultar junio
        $junio = $servicio->calcular(
            $this->empleado,
            '2026-06-01',
            '2026-06-30'
        );

        // Consultar julio
        $julio = $servicio->calcular(
            $this->empleado,
            '2026-07-01',
            '2026-07-31'
        );

        // Consultar ambos meses
        $total = $servicio->calcular(
            $this->empleado,
            '2026-06-01',
            '2026-07-31'
        );

        // Comprobar junio
        $this->assertEquals(8, $junio['horas_planificadas']);
        $this->assertEquals(7.5, $junio['horas_realizadas']);

        // Comprobar julio
        $this->assertEquals(6, $julio['horas_planificadas']);
        $this->assertEquals(5.5, $julio['horas_realizadas']);

        // Comprobar acumulado
        $this->assertEquals(14, $total['horas_planificadas']);
        $this->assertEquals(13, $total['horas_realizadas']);
    }

    public function test_employee_hours_separates_cancelled_and_draft_shifts(): void
    {
        // Turno cancelado de 6 horas
        $cancelado = $this->turno->replicate();

        $cancelado->fill([
            'fecha' => '2026-06-11',
            'hora_inicio' => '2026-06-11 10:00:00',
            'hora_fin' => '2026-06-11 16:00:00',
            'estado' => Turno::ESTADO_CANCELADO,
        ]);

        $cancelado->save();

        Asignacion::create([
            'turno_id' => $cancelado->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        // Turno en borrador de 4 horas
        $borrador = $this->turno->replicate();

        $borrador->fill([
            'fecha' => '2026-06-12',
            'hora_inicio' => '2026-06-12 10:00:00',
            'hora_fin' => '2026-06-12 14:00:00',
            'estado' => Turno::ESTADO_BORRADOR,
        ]);

        $borrador->save();

        Asignacion::create([
            'turno_id' => $borrador->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        // Calcular resumen de junio
        $servicio = new \App\Services\EmployeeHoursSummaryService();

        $resumen = $servicio->calcular(
            $this->empleado,
            '2026-06-01',
            '2026-06-30'
        );

        // Solo cuentan las horas publicadas
        $this->assertEquals(8, $resumen['horas_planificadas']);

        // Horas realmente trabajadas y validadas
        $this->assertEquals(7.5, $resumen['horas_realizadas']);

        // Horas pendientes
        $this->assertEquals(0, $resumen['horas_pendientes']);

        // Horas canceladas: solo historial
        $this->assertEquals(6, $resumen['horas_canceladas']);

        // Horas todavía en borrador
        $this->assertEquals(4, $resumen['horas_borrador']);
    }

    public function test_employee_hours_separates_validated_and_pending(): void
    {
        // Carlos ya tiene 8 h planificadas y 7,5 h validadas.
        // Creamos otro turno de 4 horas.
        $turnoPendiente = $this->turno->replicate();

        $turnoPendiente->fill([
            'fecha' => '2026-06-11',
            'hora_inicio' => '2026-06-11 10:00:00',
            'hora_fin' => '2026-06-11 14:00:00',
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $turnoPendiente->save();

        // Asignación del mismo trabajador
        $asignacion = Asignacion::create([
            'turno_id' => $turnoPendiente->id,
            'empleado_id' => $this->empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Registra 3,5 horas pendientes de validar
        RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => '2026-06-11 10:00:00',
            'fin_real' => '2026-06-11 14:00:00',
            'descanso_minutos' => 30,
            'horas_computables' => 3.5,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
        ]);

        $servicio = new \App\Services\EmployeeHoursSummaryService();

        $resumen = $servicio->calcular(
            $this->empleado,
            '2026-06-01',
            '2026-06-30'
        );

        // 8 + 4 horas planificadas
        $this->assertEquals(12, $resumen['horas_planificadas']);

        // Solo las 7,5 horas validadas
        $this->assertEquals(7.5, $resumen['horas_realizadas']);

        // Las 3,5 horas pendientes por separado
        $this->assertEquals(3.5, $resumen['horas_pendientes']);

        $this->assertEquals(0, $resumen['horas_canceladas']);
        $this->assertEquals(0, $resumen['horas_borrador']);
    }

    public function test_employee_inherits_category_rate_and_reverts_after_temporary_rate(): void
    {
        // Eliminar las tarifas del escenario inicial de prueba.
        TarifaCoste::query()->delete();

        // Tarifa general de la categoría: 15 €/hora.
        TarifaCoste::create([
            'categoria_id' => $this->empleado->categoria_id,
            'empleado_id' => null,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        // Excepción temporal del empleado durante el verano.
        TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'categoria_id' => null,
            'coste_hora' => 22,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-08-31',
        ]);

        // Junio: tarifa de categoría.
        $this->assertEquals(
            15,
            $this->empleado->tarifaEnFecha('2026-06-30')
        );

        // Primer día de julio: tarifa personalizada.
        $this->assertEquals(
            22,
            $this->empleado->tarifaEnFecha('2026-07-01')
        );

        // Último día de agosto: sigue la personalizada.
        $this->assertEquals(
            22,
            $this->empleado->tarifaEnFecha('2026-08-31')
        );

        // Septiembre: vuelve automáticamente a la categoría.
        $this->assertEquals(
            15,
            $this->empleado->tarifaEnFecha('2026-09-01')
        );
    }

    public function test_project_costs_respect_temporary_employee_rates(): void
    {
        // Limpiar tarifas iniciales de este escenario.
        TarifaCoste::query()->delete();

        // Tarifa habitual de categoría: 15 €/hora.
        TarifaCoste::create([
            'categoria_id' => $this->empleado->categoria_id,
            'empleado_id' => null,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
        ]);

        // Excepción de verano: 22 €/hora.
        TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'categoria_id' => null,
            'coste_hora' => 22,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-08-31',
        ]);

        // El escenario inicial ya incluye un turno en junio.
        // Añadimos otro en julio y otro en septiembre.
        foreach (['2026-07-10', '2026-09-10'] as $fecha) {

            $turno = $this->turno->replicate();

            $turno->fill([
                'fecha' => $fecha,
                'hora_inicio' => Carbon::parse("$fecha 10:00:00"),
                'hora_fin' => Carbon::parse("$fecha 18:00:00"),
                'estado' => Turno::ESTADO_PUBLICADO,
            ]);

            $turno->save();

            $asignacion = Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $this->empleado->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);

            RegistroHora::create([
                'asignacion_id' => $asignacion->id,
                'inicio_real' => Carbon::parse("$fecha 10:00:00"),
                'fin_real' => Carbon::parse("$fecha 18:00:00"),
                'descanso_minutos' => 30,
                'horas_computables' => 7.5,
                'estado' => RegistroHora::ESTADO_VALIDADO,
            ]);
        }

        // Consultar los costes de cada mes por separado.
        $junio = $this->econService->calcularInformeProyecto(
            $this->proyecto,
            '2026-06-01',
            '2026-06-30'
        );

        $julio = $this->econService->calcularInformeProyecto(
            $this->proyecto,
            '2026-07-01',
            '2026-07-31'
        );

        $septiembre = $this->econService->calcularInformeProyecto(
            $this->proyecto,
            '2026-09-01',
            '2026-09-30'
        );

        // JUNIO: 15 €/hora.
        $this->assertEquals(120, $junio['coste_personal_previsto']);
        $this->assertEquals(112.5, $junio['coste_personal_real']);

        // JULIO: 22 €/hora.
        $this->assertEquals(176, $julio['coste_personal_previsto']);
        $this->assertEquals(165, $julio['coste_personal_real']);

        // SEPTIEMBRE: vuelve a 15 €/hora.
        $this->assertEquals(120, $septiembre['coste_personal_previsto']);
        $this->assertEquals(112.5, $septiembre['coste_personal_real']);
    }

    public function test_updating_historical_rate_recalculates_past_project_costs(): void
    {
        // 1. Consultar los costes originales de junio.
        $antes = $this->econService->calcularInformeProyecto(
            $this->proyecto,
            '2026-06-01',
            '2026-06-30'
        );

        $this->assertEquals(120, $antes['coste_personal_previsto']);
        $this->assertEquals(112.5, $antes['coste_personal_real']);

        // 2. Modificar la tarifa histórica de 15 € a 25 €.
        $tarifa = TarifaCoste::where(
            'empleado_id',
            $this->empleado->id
        )->firstOrFail();

        $tarifa->coste_hora = 25;
        $tarifa->save();

        // 3. Consultar nuevamente el mismo período.
        $despues = $this->econService->calcularInformeProyecto(
            $this->proyecto,
            '2026-06-01',
            '2026-06-30'
        );

        // 4. Comprobar si los costes han cambiado.
        $this->assertEquals(200, $despues['coste_personal_previsto']);
        $this->assertEquals(187.5, $despues['coste_personal_real']);
    }

    public function test_temporary_override_restores_previous_employee_rate(): void
    {
        // El escenario inicial ya tiene una tarifa individual
        // de 15 €/hora desde el 01/01/2026, sin fecha de fin.

        $tarifaOriginal = TarifaCoste::where(
            'empleado_id',
            $this->empleado->id
        )->firstOrFail();

        // Crear una excepción temporal de 22 €/hora.
        app(\App\Services\CostRatePeriodService::class)
            ->crearExcepcionTemporalEmpleado(
                $this->empleado->id,
                22.0,
                '2026-07-01',
                '2026-08-31'
            );

        // La tarifa original debe terminar el 30 de junio.
        $tarifaOriginal->refresh();

        $this->assertEquals(
            '2026-06-30',
            $tarifaOriginal->fecha_fin->toDateString()
        );

        // Deben existir tres períodos.
        $this->assertEquals(
            3,
            TarifaCoste::where(
                'empleado_id',
                $this->empleado->id
            )->count()
        );

        // Comprobar las tarifas aplicadas.
        $this->assertEquals(
            15,
            $this->empleado->tarifaEnFecha('2026-06-30')
        );

        $this->assertEquals(
            22,
            $this->empleado->tarifaEnFecha('2026-07-01')
        );

        $this->assertEquals(
            22,
            $this->empleado->tarifaEnFecha('2026-08-31')
        );

        $this->assertEquals(
            15,
            $this->empleado->tarifaEnFecha('2026-09-01')
        );
    }

    public function test_temporary_override_rejects_conflicts_without_modifying_rates(): void
    {
        // Tarifa original: 15 €/hora desde enero.
        $original = TarifaCoste::where(
            'empleado_id',
            $this->empleado->id
        )->firstOrFail();

        // Simulamos una tarifa futura que ya existe.
        TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => 30,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => null,
        ]);

        // Intentamos crear una excepción que entra en conflicto.
        try {
            app(\App\Services\CostRatePeriodService::class)
                ->crearExcepcionTemporalEmpleado(
                    $this->empleado->id,
                    22.0,
                    '2026-07-01',
                    '2026-08-31'
                );

            $this->fail('Se esperaba un error por solapamiento.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('fecha_inicio', $e->errors());
        }

        // La tarifa original debe seguir intacta.
        $original->refresh();

        $this->assertNull($original->fecha_fin);
        $this->assertEquals(15, $original->coste_hora);

        // No se debe haber creado ninguna tarifa adicional.
        $this->assertEquals(
            2,
            TarifaCoste::where(
                'empleado_id',
                $this->empleado->id
            )->count()
        );
    }
}
