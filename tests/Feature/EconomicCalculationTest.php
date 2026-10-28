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
}
