<?php

namespace Tests\Feature;

use App\Models\CierreEconomico;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\RegistroHora;
use App\Models\Turno;
use App\Services\EconomicClosingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use App\Models\TarifaCoste;
use App\Services\EconomicReportService;

class CierreEconomicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_dos_cierres_del_mismo_proyecto_y_mes(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $datos = [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 10,
            'datos_consolidados' => [
                'ingresos_totales' => 1000,
                'coste_personal_real' => 600,
                'resultado_estimado' => 400,
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ];

        // Primer cierre: permitido.
        CierreEconomico::create($datos);

        $this->assertDatabaseCount('cierre_economicos', 1);

        // Segundo cierre del mismo proyecto y mes: prohibido.
        try {
            CierreEconomico::create($datos);

            $this->fail('Se permitió duplicar un cierre económico.');
        } catch (QueryException $e) {
            // La base de datos debe impedir el duplicado.
            $this->assertDatabaseCount('cierre_economicos', 1);
        }

        // Otro mes del mismo proyecto: permitido.
        CierreEconomico::create([
            ...$datos,
            'mes' => 11,
        ]);

        $this->assertDatabaseCount('cierre_economicos', 2);
    }

    public function test_no_permite_cerrar_periodo_con_horas_pendientes(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::ACCOUNTANT],
            ['display_name' => 'Contable']
        );

        $usuario->roles()->syncWithoutDetaching([$rol->id]);

        $this->actingAs($usuario);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Carlos',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
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
            'nombre' => 'Proyecto de verano',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => Carbon::parse('2026-07-10 10:00:00'),
            'hora_fin' => Carbon::parse('2026-07-10 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $asignacion = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => Carbon::parse('2026-07-10 10:00:00'),
            'fin_real' => Carbon::parse('2026-07-10 18:00:00'),
            'horas_computables' => 8,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
        ]);

        try {
            app(EconomicClosingService::class)->cerrarPeriodo(
                $proyecto,
                2026,
                7,
                $usuario
            );

            $this->fail('MiTa permitió cerrar un período con horas pendientes.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertDatabaseCount('cierre_economicos', 0);
    }

    public function test_no_permite_cerrar_con_asignaciones_sin_registro_de_horas(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::ACCOUNTANT],
            ['display_name' => 'Contable']
        );

        $usuario->roles()->syncWithoutDetaching([$rol->id]);

        $this->actingAs($usuario);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Carlos',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        // Tarifa válida para evitar que el cierre falle por costes incompletos.
        TarifaCoste::create([
            'empleado_id' => $empleado->id,
            'coste_hora' => 15.00,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
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
            'nombre' => 'Proyecto de verano',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Turno publicado y finalizado.
        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => Carbon::parse('2026-07-10 10:00:00'),
            'hora_fin' => Carbon::parse('2026-07-10 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Asignación confirmada, pero SIN registro de horas.
        Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        try {
            app(EconomicClosingService::class)->cerrarPeriodo(
                $proyecto,
                2026,
                7,
                $usuario
            );

            $this->fail(
                'MiTa permitió cerrar un período con asignaciones sin horas.'
            );
        } catch (ValidationException $e) {
            // dump($e->errors());

            $this->assertArrayHasKey('horas', $e->errors());
        }

        $this->assertDatabaseCount('cierre_economicos', 0);
    }
    public function test_cierre_conserva_importes_tras_modificar_tarifa(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::firstOrCreate(
            ['name' => \App\Models\Role::ACCOUNTANT],
            ['display_name' => 'Contable']
        );

        $usuario->roles()->syncWithoutDetaching([$rol->id]);

        $this->actingAs($usuario);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Carlos',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $tarifa = TarifaCoste::create([
            'empleado_id' => $empleado->id,
            'coste_hora' => 15,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => null,
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
            'nombre' => 'Proyecto de verano',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => Carbon::parse('2026-07-10 10:00:00'),
            'hora_fin' => Carbon::parse('2026-07-10 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $asignacion = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => Carbon::parse('2026-07-10 10:00:00'),
            'fin_real' => Carbon::parse('2026-07-10 18:00:00'),
            'horas_computables' => 8,
            'estado' => RegistroHora::ESTADO_VALIDADO,
        ]);

        // 1. Cerrar julio con una tarifa de 15 €/hora.
        $cierre = app(EconomicClosingService::class)
            ->cerrarPeriodo($proyecto, 2026, 7, $usuario);

        $this->assertEquals(
            120,
            $cierre->datos_consolidados['coste_personal_real']
        );

        // 2. Modificar posteriormente la tarifa a 25 €/hora.
        $tarifa->update([
            'coste_hora' => 25,
        ]);

        // 3. El cálculo dinámico cambia a 200 €.
        $informeActual = app(
            \App\Services\EconomicCalculationService::class
        )->calcularInformeProyecto(
            $proyecto,
            '2026-07-01',
            '2026-07-31'
        );

        $this->assertEquals(
            200,
            $informeActual['coste_personal_real']
        );

        // 4. El cierre almacenado conserva los 120 € originales.
        $cierre->refresh();

        $this->assertEquals(
            120,
            $cierre->datos_consolidados['coste_personal_real']
        );

        // 5. Sigue existiendo un único cierre.
        $this->assertDatabaseCount('cierre_economicos', 1);
    }
    public function test_consulta_mes_cerrado_devuelve_datos_consolidados(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Informe oficial que se guardó al cerrar julio.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => ['Informe consolidado de julio'],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Consultar julio completo mediante el nuevo servicio.
        $informe = app(EconomicReportService::class)
            ->obtenerInformeEconomico(
                $proyecto,
                '2026-07-01',
                '2026-07-31'
            );

        // Debe devolver los importes oficiales del cierre.
        $this->assertEquals(120, $informe['coste_personal_real']);
        $this->assertEquals(200, $informe['ingresos_totales']);
        $this->assertEquals(50, $informe['resultado_estimado']);

        // Identificar expresamente el origen del informe.
        $this->assertSame('consolidado', $informe['tipo_informe']);
    }

    public function test_consulta_parcial_mes_cerrado_es_dinamica(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Julio tiene un cierre oficial con 120 € de coste.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => ['Informe oficial de julio'],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Consultamos solamente una parte de julio.
        $informe = app(EconomicReportService::class)
            ->obtenerInformeEconomico(
                $proyecto,
                '2026-07-10',
                '2026-07-20'
            );

        // No debe devolver los 120 € del cierre completo.
        // En este escenario no existen turnos ni movimientos.
        $this->assertEquals(0, $informe['coste_personal_real']);

        // Debe identificarse como cálculo dinámico.
        $this->assertSame('dinamico', $informe['tipo_informe']);

        $this->assertTrue(
            $informe['advertencia_cierre'] ?? false,
            'La consulta parcial de un mes cerrado debe mostrar una advertencia.'
        );
    }

    public function test_informe_mixto_combina_mes_cerrado_y_abierto(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto de verano',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Julio: período cerrado con valores consolidados.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => ['Julio consolidado'],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Agosto está abierto y no tiene actividad.
        $informe = app(EconomicReportService::class)
            ->obtenerInformeEconomico(
                $proyecto,
                '2026-07-01',
                '2026-08-31'
            );

        $this->assertSame('mixto', $informe['tipo_informe']);
        $this->assertEquals(8, $informe['horas_realizadas']);
        $this->assertEquals(120, $informe['coste_personal_real']);
        $this->assertEquals(200, $informe['ingresos_totales']);
        $this->assertEquals(30, $informe['gastos_adicionales']);
        $this->assertEquals(50, $informe['resultado_estimado']);
    }

    public function test_informe_mixto_suma_movimientos_del_mes_abierto(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto de verano',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Julio cerrado.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => ['Julio consolidado'],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Agosto abierto: ingreso de 500 €.
        \App\Models\MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => \App\Models\MovimientoEconomico::TIPO_INGRESO,
            'fecha' => '2026-08-10',
            'concepto' => 'Factura de agosto',
            'importe' => 500,
        ]);

        // Agosto abierto: gasto de 100 €.
        \App\Models\MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => \App\Models\MovimientoEconomico::TIPO_GASTO,
            'fecha' => '2026-08-15',
            'concepto' => 'Material de agosto',
            'importe' => 100,
        ]);

        $informe = app(EconomicReportService::class)
            ->obtenerInformeEconomico(
                $proyecto,
                '2026-07-01',
                '2026-08-31'
            );

        $this->assertSame('mixto', $informe['tipo_informe']);

        // Ingresos: 200 € de julio + 500 € de agosto.
        $this->assertEquals(700, $informe['ingresos_totales']);

        // Gastos: 30 € de julio + 100 € de agosto.
        $this->assertEquals(130, $informe['gastos_adicionales']);

        // Coste personal: 120 € de julio.
        $this->assertEquals(120, $informe['coste_personal_real']);

        // Resultado: 700 - 130 - 120 = 450 €.
        $this->assertEquals(450, $informe['resultado_estimado']);
    }
    public function test_informe_mixto_entre_dos_anios(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto anual',
            'fecha_inicio' => '2025-01-01',
        ]);

        // Diciembre de 2025: cerrado.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2025,
            'mes' => 12,
            'datos_consolidados' => [
                'horas_previstas' => 0,
                'horas_realizadas' => 0,
                'coste_personal_previsto' => 0,
                'coste_personal_real' => 0,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 1000,
                'gastos_adicionales' => 0,
                'resultado_estimado' => 1000,
                'desglose_explicativo' => ['Diciembre consolidado'],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Enero de 2026: abierto.
        \App\Models\MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => \App\Models\MovimientoEconomico::TIPO_INGRESO,
            'fecha' => '2026-01-15',
            'concepto' => 'Factura enero',
            'importe' => 500,
        ]);

        $informe = app(EconomicReportService::class)
            ->obtenerInformeEconomico(
                $proyecto,
                '2025-12-01',
                '2026-01-31'
            );

        $this->assertSame('mixto', $informe['tipo_informe']);
        $this->assertEquals(1500, $informe['ingresos_totales']);
        $this->assertEquals(1500, $informe['resultado_estimado']);
    }

    public function test_no_permite_modificar_un_cierre_economico(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $cierre = CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'coste_personal_real' => 120,
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Intentar modificar un cierre consolidado.
        try {
            $cierre->update([
                'datos_consolidados' => [
                    'coste_personal_real' => 9999,
                ],
            ]);

            $this->fail(
                'MiTa permitió modificar un cierre económico consolidado.'
            );
        } catch (\LogicException $e) {
            // La modificación debe estar prohibida.
        }

        // Recuperar los datos originales desde la base de datos.
        $cierre->refresh();

        $this->assertEquals(
            120,
            $cierre->datos_consolidados['coste_personal_real']
        );
    }
    public function test_no_permite_eliminar_un_cierre_economico(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $cierre = CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'coste_personal_real' => 120,
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // La eliminación debe estar prohibida.
        $this->expectException(\LogicException::class);

        try {
            $cierre->delete();
        } finally {
            // El registro debe continuar existiendo.
            $this->assertDatabaseHas('cierre_economicos', [
                'id' => $cierre->id,
                'proyecto_id' => $proyecto->id,
            ]);
        }
    }
    public function test_no_permite_modificar_cierre_mediante_sql_directo(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $cierre = CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'coste_personal_real' => 120,
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Intentar saltarse los eventos de Eloquent.
        $bloqueado = false;

        try {
            \Illuminate\Support\Facades\DB::table('cierre_economicos')
                ->where('id', $cierre->id)
                ->update([
                    'datos_consolidados' => json_encode([
                        'coste_personal_real' => 9999,
                    ]),
                ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $bloqueado = true;
        }

        $this->assertTrue(
            $bloqueado,
            'PostgreSQL permitió modificar directamente un cierre consolidado.'
        );

        $this->assertEquals(
            120,
            CierreEconomico::findOrFail($cierre->id)
                ->datos_consolidados['coste_personal_real']
        );
    }
    public function test_no_permite_eliminar_cierre_mediante_sql_directo(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $cierre = CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'coste_personal_real' => 120,
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Intentar eliminar el cierre saltándose Eloquent.
        $bloqueado = false;

        try {
            \Illuminate\Support\Facades\DB::table('cierre_economicos')
                ->where('id', $cierre->id)
                ->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            $bloqueado = true;
        }

        $this->assertTrue(
            $bloqueado,
            'La base de datos permitió eliminar directamente un cierre económico.'
        );

        $this->assertDatabaseHas('cierre_economicos', [
            'id' => $cierre->id,
            'proyecto_id' => $proyecto->id,
        ]);
    }

    public function test_empleado_no_puede_cerrar_periodo_economico(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::EMPLOYEE,
            'display_name' => 'Empleado',
        ]);

        $usuario->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($usuario);

        try {
            app(EconomicClosingService::class)
                ->cerrarPeriodo($proyecto, 2026, 7, $usuario);

            $this->fail(
                'MiTa permitió a un empleado cerrar un período económico.'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertDatabaseMissing('cierre_economicos', [
                'proyecto_id' => $proyecto->id,
                'anio' => 2026,
                'mes' => 7,
            ]);
        }
    }
    public function test_contable_puede_cerrar_periodo_economico(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ACCOUNTANT,
            'display_name' => 'Contable',
        ]);

        $usuario->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($usuario);

        $cierre = app(EconomicClosingService::class)
            ->cerrarPeriodo($proyecto, 2026, 7, $usuario);

        $this->assertDatabaseHas('cierre_economicos', [
            'id' => $cierre->id,
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'cerrado_por' => $usuario->id,
        ]);

        $this->assertEquals(
            0,
            $cierre->datos_consolidados['coste_personal_real']
        );
    }
    public function test_no_permite_cerrar_periodo_a_nombre_de_otro_usuario(): void
    {
        $contable = User::factory()->create();
        $otroUsuario = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ACCOUNTANT,
            'display_name' => 'Contable',
        ]);

        $contable->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Juan está autenticado.
        $this->actingAs($contable);

        // Pero intenta registrar el cierre a nombre de Pedro.
        try {
            app(EconomicClosingService::class)
                ->cerrarPeriodo($proyecto, 2026, 7, $otroUsuario);

            $this->fail(
                'MiTa permitió cerrar un período a nombre de otro usuario.'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertDatabaseMissing('cierre_economicos', [
                'proyecto_id' => $proyecto->id,
                'anio' => 2026,
                'mes' => 7,
            ]);
        }
    }
    public function test_administrador_puede_cerrar_periodo_economico(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        $cierre = app(EconomicClosingService::class)
            ->cerrarPeriodo($proyecto, 2026, 7, $administrador);

        $this->assertDatabaseHas('cierre_economicos', [
            'id' => $cierre->id,
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'cerrado_por' => $administrador->id,
        ]);

        $this->assertSame(
            $administrador->id,
            $cierre->cerrado_por
        );
    }

    public function test_rrhh_no_puede_cerrar_periodo_economico(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::RRHH,
            'display_name' => 'Recursos Humanos',
        ]);

        $usuario->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($usuario);

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        try {
            app(EconomicClosingService::class)
                ->cerrarPeriodo($proyecto, 2026, 7, $usuario);
        } finally {
            $this->assertDatabaseMissing('cierre_economicos', [
                'proyecto_id' => $proyecto->id,
                'anio' => 2026,
                'mes' => 7,
            ]);
        }
    }
    public function test_planificador_no_puede_cerrar_periodo_economico(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::PLANNER,
            'display_name' => 'Planificador',
        ]);

        $usuario->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($usuario);

        $this->expectException(
            \Illuminate\Auth\Access\AuthorizationException::class
        );

        try {
            app(EconomicClosingService::class)
                ->cerrarPeriodo($proyecto, 2026, 7, $usuario);
        } finally {
            $this->assertDatabaseMissing('cierre_economicos', [
                'proyecto_id' => $proyecto->id,
                'anio' => 2026,
                'mes' => 7,
            ]);
        }
    }
    public function test_consulta_por_instalacion_de_mes_cerrado_muestra_advertencia(): void
{
    $usuario = User::factory()->create();

    $cliente = Cliente::create([
        'razon_social' => 'Ayuntamiento de Pruebas',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Municipal',
        'fecha_inicio' => '2026-01-01',
    ]);

    CierreEconomico::create([
        'proyecto_id' => $proyecto->id,
        'anio' => 2026,
        'mes' => 7,
        'datos_consolidados' => [
            'horas_previstas' => 8,
            'horas_realizadas' => 8,
            'coste_personal_previsto' => 120,
            'coste_personal_real' => 120,
            'coste_previsto_incompleto' => false,
            'coste_real_incompleto' => false,
            'ingresos_totales' => 200,
            'gastos_adicionales' => 30,
            'resultado_estimado' => 50,
            'desglose_explicativo' => [],
        ],
        'cerrado_por' => $usuario->id,
        'cerrado_en' => now(),
    ]);

    // El servicio recibe un filtro de instalación.
    $informe = app(EconomicReportService::class)
        ->obtenerInformeEconomico(
            $proyecto,
            '2026-07-01',
            '2026-07-31',
            1
        );

    $this->assertSame('dinamico', $informe['tipo_informe']);

    $this->assertTrue(
        $informe['advertencia_cierre'] ?? false,
        'El informe por instalación debe advertir que existe un cierre oficial.'
    );
}
}
