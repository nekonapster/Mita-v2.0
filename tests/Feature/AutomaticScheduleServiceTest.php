<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Services\AutomaticScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Contrato;
use App\Models\Ausencia;

class AutomaticScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generador_respeta_asignaciones_manuales(): void
    {
        // Preparar los datos.
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
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
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 2,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Asignación manual que nunca debe modificarse.
        $asignacionManual = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Ejecutar el generador.
        $generador = app(AutomaticScheduleService::class);

        $generador->generar($proyecto, '2026-06-15', '2026-06-15');

        // Comprobar que la asignación manual permanece intacta.
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionManual->id,
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }
    public function test_generador_cubre_plaza_vacante_sin_modificar_asignacion_manual(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $juan = Empleado::create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
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
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 2,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $manual = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $juan->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15'
        );

        // La asignación manual sigue intacta.
        $this->assertDatabaseHas('asignaciones', [
            'id' => $manual->id,
            'empleado_id' => $juan->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Pedro ocupa la plaza vacante.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        // El turno queda cubierto, sin sobreasignaciones.
        $this->assertSame(2, $turno->plazasAsignadas());
    }

    public function test_generador_no_asigna_empleado_con_turno_solapado(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Test',
        ]);

        $instalacionA = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina A',
        ]);

        $instalacionB = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina B',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $datosTurno = [
            'proyecto_id' => $proyecto->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ];

        // Pedro ya está trabajando en Piscina A.
        $turnoA = Turno::create($datosTurno + [
            'instalacion_id' => $instalacionA->id,
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
        ]);

        $asignacionManual = Asignacion::create([
            'turno_id' => $turnoA->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Piscina B necesita un socorrista en horario coincidente.
        $turnoB = Turno::create($datosTurno + [
            'instalacion_id' => $instalacionB->id,
            'hora_inicio' => Carbon::parse('2026-06-15 12:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 20:00:00'),
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15'
        );

        // Pedro mantiene su asignación manual.
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionManual->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // No debe recibir otra asignación en Piscina B.
        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turnoB->id,
            'empleado_id' => $pedro->id,
        ]);

        // La plaza permanece libre porque no hay otro candidato.
        $this->assertSame(1, $turnoB->plazasDisponibles());
    }

    public function test_generador_respeta_ausencias_aprobadas(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
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
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Pedro tiene vacaciones aprobadas que coinciden con el turno.
        Ausencia::create([
            'empleado_id' => $pedro->id,
            'tipo' => Ausencia::TIPO_VACACIONES,
            'fecha_hora_inicio' => Carbon::parse('2026-06-15 08:00:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-06-15 20:00:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15'
        );

        // Pedro no debe recibir ninguna asignación.
        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $pedro->id,
        ]);

        // El turno sigue teniendo una plaza vacante.
        $this->assertSame(1, $turno->plazasDisponibles());
    }
    public function test_ejecutar_generador_dos_veces_no_duplica_asignaciones(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
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
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $generador = app(AutomaticScheduleService::class);

        // Primera ejecución.
        $generador->generar($proyecto, '2026-06-15', '2026-06-15');

        // Segunda ejecución sobre el mismo período.
        $generador->generar($proyecto, '2026-06-15', '2026-06-15');

        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        $this->assertSame(1, $turno->plazasAsignadas());

        $this->assertSame(
            1,
            Asignacion::where('turno_id', $turno->id)
                ->where('empleado_id', $pedro->id)
                ->count()
        );
    }
    public function test_generador_ignora_turnos_cancelados(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
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
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_CANCELADO,
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15'
        );

        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $pedro->id,
        ]);

        $this->assertSame(
            0,
            Asignacion::where('turno_id', $turno->id)->count()
        );
    }
    public function test_generador_solo_asigna_en_instalacion_seleccionada(): void
    {
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

        $cavaleri = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Centro Acuático Cavaleri',
        ]);

        $otraPiscina = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Otra Piscina',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $proyecto->instalaciones()->attach([
            $cavaleri->id,
            $otraPiscina->id,
        ]);

        $turnoCavaleri = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $cavaleri->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $turnoOtraPiscina = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $otraPiscina->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 14:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15',
            $cavaleri->id
        );

        // Debe cubrir Cavaleri.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turnoCavaleri->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        // No debe tocar la otra piscina.
        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turnoOtraPiscina->id,
        ]);
    }
    public function test_generador_sin_filtro_cubre_todas_las_instalaciones(): void
    {
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

        $cavaleri = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Cavaleri',
        ]);

        $otraPiscina = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Otra Piscina',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
        ]);

        $proyecto->instalaciones()->attach([
            $cavaleri->id,
            $otraPiscina->id,
        ]);

        $turnoCavaleri = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $cavaleri->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $turnoOtraPiscina = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $otraPiscina->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 14:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Sin indicar instalación: generación global del proyecto.
        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15'
        );

        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turnoCavaleri->id,
            'empleado_id' => $empleado->id,
        ]);

        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turnoOtraPiscina->id,
            'empleado_id' => $empleado->id,
        ]);
    }
    public function test_generador_prioriza_empleado_con_menos_horas_asignadas(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $juan = Empleado::create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        foreach ([$juan, $pedro] as $empleado) {
            Contrato::create([
                'empleado_id' => $empleado->id,
                'tipo_contrato' => 'Indefinido',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-12-31',
                'jornada_semanal_horas' => 40,
            ]);
        }

        $cliente = Cliente::create(['razon_social' => 'Cliente Test']);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
        ]);

        $actividad = Actividad::create(['nombre' => 'Socorrismo']);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $crearTurno = function (string $fecha) use (
            $proyecto,
            $instalacion,
            $actividad,
            $categoria
        ) {
            return Turno::create([
                'proyecto_id' => $proyecto->id,
                'instalacion_id' => $instalacion->id,
                'actividad_id' => $actividad->id,
                'categoria_id' => $categoria->id,
                'fecha' => $fecha,
                'hora_inicio' => Carbon::parse("$fecha 08:00:00", 'Europe/Madrid'),
                'hora_fin' => Carbon::parse("$fecha 16:00:00", 'Europe/Madrid'),
                'plazas_necesarias' => 1,
                'estado' => Turno::ESTADO_PUBLICADO,
            ]);
        };

        // Juan ya tiene 24 horas asignadas.
        foreach (['2026-06-15', '2026-06-16', '2026-06-17'] as $fecha) {
            $turno = $crearTurno($fecha);

            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $juan->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // Pedro solamente tiene 8 horas asignadas.
        $turnoPedro = $crearTurno('2026-06-15');

        Asignacion::create([
            'turno_id' => $turnoPedro->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Nueva plaza vacante.
        $turnoVacante = $crearTurno('2026-06-18');

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-18'
        );

        // Pedro debe tener prioridad por acumular menos horas.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turnoVacante->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turnoVacante->id,
            'empleado_id' => $juan->id,
        ]);
    }
    public function test_generador_no_cuenta_horas_canceladas_para_reparto(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $juan = Empleado::create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        foreach ([$juan, $pedro] as $empleado) {
            Contrato::create([
                'empleado_id' => $empleado->id,
                'tipo_contrato' => 'Indefinido',
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-12-31',
                'jornada_semanal_horas' => 40,
            ]);
        }

        $cliente = Cliente::create(['razon_social' => 'Cliente Test']);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
        ]);

        $actividad = Actividad::create(['nombre' => 'Socorrismo']);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $crearTurno = function (string $fecha) use (
            $proyecto,
            $instalacion,
            $actividad,
            $categoria
        ) {
            return Turno::create([
                'proyecto_id' => $proyecto->id,
                'instalacion_id' => $instalacion->id,
                'actividad_id' => $actividad->id,
                'categoria_id' => $categoria->id,
                'fecha' => $fecha,
                'hora_inicio' => Carbon::parse("$fecha 08:00:00", 'Europe/Madrid'),
                'hora_fin' => Carbon::parse("$fecha 16:00:00", 'Europe/Madrid'),
                'plazas_necesarias' => 1,
                'estado' => Turno::ESTADO_PUBLICADO,
            ]);
        };

        // Juan: 16 horas en asignaciones canceladas.
        foreach (['2026-06-15', '2026-06-16'] as $fecha) {
            $turno = $crearTurno($fecha);

            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $juan->id,
                'estado' => Asignacion::ESTADO_CANCELADO,
            ]);

            // El turno sigue publicado, pero no debe entrar
            // en esta ejecución del generador.
            $turno->update([
                'fecha' => '2026-06-10',
                'hora_inicio' => Carbon::parse('2026-06-10 08:00:00', 'Europe/Madrid'),
                'hora_fin' => Carbon::parse('2026-06-10 16:00:00', 'Europe/Madrid'),
            ]);
        }

        // Pedro: 8 horas reales de planificación activa.
        $turnoPedro = $crearTurno('2026-06-17');

        Asignacion::create([
            'turno_id' => $turnoPedro->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        // Nueva plaza vacante.
        $turnoVacante = $crearTurno('2026-06-18');

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-18'
        );

        // Juan tiene cero horas activas; Pedro tiene ocho.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turnoVacante->id,
            'empleado_id' => $juan->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turnoVacante->id,
            'empleado_id' => $pedro->id,
        ]);
    }

    public function test_generador_cubre_vacante_de_asignacion_cancelada(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $juan = Empleado::create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 40,
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
            'hora_inicio' => Carbon::parse('2026-06-15 10:00:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00:00', 'Europe/Madrid'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Juan estaba asignado, pero se canceló su participación.
        $asignacionCancelada = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $juan->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        // El turno continúa publicado y tiene una plaza libre.
        $this->assertSame(1, $turno->plazasDisponibles());

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-15',
            '2026-06-15'
        );

        // Pedro debe ocupar la plaza vacante.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        // La asignación cancelada de Juan debe conservarse.
        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionCancelada->id,
            'turno_id' => $turno->id,
            'empleado_id' => $juan->id,
            'estado' => Asignacion::ESTADO_CANCELADO,
        ]);

        // La plaza queda cubierta, sin sobreasignaciones.
        $this->assertSame(0, $turno->fresh()->plazasDisponibles());
    }

    public function test_generador_no_supera_jornada_semanal_contratada(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 20,
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

        $datosTurno = [
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ];

        // Lunes: 10 horas ya asignadas.
        $turnoLunes = Turno::create($datosTurno + [
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-15 18:00', 'Europe/Madrid'),
        ]);

        // Martes: 8 horas ya asignadas.
        $turnoMartes = Turno::create($datosTurno + [
            'fecha' => '2026-06-16',
            'hora_inicio' => Carbon::parse('2026-06-16 08:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-16 16:00', 'Europe/Madrid'),
        ]);

        foreach ([$turnoLunes, $turnoMartes] as $turno) {
            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $pedro->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // Miércoles: nuevo turno de 8 horas.
        $turnoVacante = Turno::create($datosTurno + [
            'fecha' => '2026-06-17',
            'hora_inicio' => Carbon::parse('2026-06-17 08:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-06-17 16:00', 'Europe/Madrid'),
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-17',
            '2026-06-17'
        );

        // Pedro tiene 18 horas: añadir 8 superaría las 20 contratadas.
        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $turnoVacante->id,
            'empleado_id' => $pedro->id,
        ]);

        // Sus asignaciones anteriores permanecen intactas.
        $this->assertSame(
            2,
            Asignacion::where('empleado_id', $pedro->id)->count()
        );

        // La vacante queda pendiente de cubrir.
        $this->assertSame(1, $turnoVacante->plazasDisponibles());
    }

    public function test_generador_reparte_horas_de_turno_nocturno_entre_semanas(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 20,
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

        $datosTurno = [
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ];

        // Domingo 22:00 a lunes 06:00 (hora española).
        // Solo 6 de sus 8 horas corresponden a la nueva semana.
        $nocturno = Turno::create($datosTurno + [
            'fecha' => '2026-06-14',
            'hora_inicio' => Carbon::parse(
                '2026-06-14 22:00',
                'Europe/Madrid'
            )->utc(),
            'hora_fin' => Carbon::parse(
                '2026-06-15 06:00',
                'Europe/Madrid'
            )->utc(),
        ]);

        // Martes: 8 horas.
        $martes = Turno::create($datosTurno + [
            'fecha' => '2026-06-16',
            'hora_inicio' => Carbon::parse(
                '2026-06-16 08:00',
                'Europe/Madrid'
            )->utc(),
            'hora_fin' => Carbon::parse(
                '2026-06-16 16:00',
                'Europe/Madrid'
            )->utc(),
        ]);

        foreach ([$nocturno, $martes] as $turno) {
            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $pedro->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // Miércoles: nuevo turno de 6 horas.
        $miercoles = Turno::create($datosTurno + [
            'fecha' => '2026-06-17',
            'hora_inicio' => Carbon::parse(
                '2026-06-17 08:00',
                'Europe/Madrid'
            )->utc(),
            'hora_fin' => Carbon::parse(
                '2026-06-17 14:00',
                'Europe/Madrid'
            )->utc(),
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-17',
            '2026-06-17'
        );

        // 6 horas del nocturno + 8 del martes + 6 del miércoles = 20.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $miercoles->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        $this->assertSame(0, $miercoles->fresh()->plazasDisponibles());
    }


    public function test_generador_valida_ambas_semanas_en_turno_nocturno(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $pedro = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Contrato::create([
            'empleado_id' => $pedro->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'jornada_semanal_horas' => 20,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Test',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
        ]);

        $actividad = Actividad::create(['nombre' => 'Socorrismo']);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2026-01-01',
        ]);

        $datosTurno = [
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ];

        $crearTurno = function ($fecha, $inicio, $fin) use ($datosTurno) {
            return Turno::create($datosTurno + [
                'fecha' => $fecha,
                'hora_inicio' => Carbon::parse($inicio, 'Europe/Madrid')->utc(),
                'hora_fin' => Carbon::parse($fin, 'Europe/Madrid')->utc(),
            ]);
        };

        // Semana anterior: 18 horas.
        $viernes = $crearTurno(
            '2026-06-12',
            '2026-06-12 08:00',
            '2026-06-12 18:00'
        );

        $sabado = $crearTurno(
            '2026-06-13',
            '2026-06-13 08:00',
            '2026-06-13 16:00'
        );

        // Semana siguiente: 10 horas.
        $lunes = $crearTurno(
            '2026-06-15',
            '2026-06-15 08:00',
            '2026-06-15 18:00'
        );

        foreach ([$viernes, $sabado, $lunes] as $turno) {
            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $pedro->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // Nueva vacante: domingo 22:00 hasta lunes 06:00.
        $nocturno = $crearTurno(
            '2026-06-14',
            '2026-06-14 22:00',
            '2026-06-15 06:00'
        );

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-14',
            '2026-06-14'
        );

        // Semana anterior: 18 + 2 = 20 horas.
        // Semana siguiente: 10 + 6 = 16 horas.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $nocturno->id,
            'empleado_id' => $pedro->id,
            'estado' => Asignacion::ESTADO_ASIGNADO,
        ]);

        $this->assertSame(0, $nocturno->fresh()->plazasDisponibles());

        // Simulamos una hora adicional ya trabajada el sábado.
        // La semana anterior pasa de 18 a 19 horas.
        $sabado->update([
            'hora_fin' => Carbon::parse(
                '2026-06-13 17:00',
                'Europe/Madrid'
            )->utc(),
        ]);

        // Eliminamos únicamente la asignación automática creada
        // para poder comprobar nuevamente el mismo turno.
        Asignacion::where('turno_id', $nocturno->id)
            ->where('empleado_id', $pedro->id)
            ->delete();

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            '2026-06-14',
            '2026-06-14'
        );

        // Semana anterior: 19 + 2 = 21 horas.
        // El generador debe rechazar la asignación.
        $this->assertDatabaseMissing('asignaciones', [
            'turno_id' => $nocturno->id,
            'empleado_id' => $pedro->id,
        ]);

        $this->assertSame(1, $nocturno->fresh()->plazasDisponibles());
    }


public function test_generador_respeta_cambio_de_jornada_entre_contratos(): void
{
    $categoria = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    $pedro = Empleado::create([
        'nombre' => 'Pedro',
        'apellidos' => 'García',
        'categoria_id' => $categoria->id,
        'activo' => true,
    ]);

    // Junio: 20 horas semanales.
    Contrato::create([
        'empleado_id' => $pedro->id,
        'tipo_contrato' => 'Temporal',
        'fecha_inicio' => '2026-06-01',
        'fecha_fin' => '2026-06-30',
        'jornada_semanal_horas' => 20,
    ]);

    // Julio: 30 horas semanales.
    Contrato::create([
        'empleado_id' => $pedro->id,
        'tipo_contrato' => 'Temporal',
        'fecha_inicio' => '2026-07-01',
        'fecha_fin' => '2026-07-31',
        'jornada_semanal_horas' => 30,
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

    $datosTurno = [
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'plazas_necesarias' => 1,
        'estado' => Turno::ESTADO_PUBLICADO,
    ];

    // Usamos semanas distintas para evitar el cambio a mitad de semana.
    foreach ([
        ['2026-06-15', '2026-06-16', '2026-06-17', false],
        ['2026-07-06', '2026-07-07', '2026-07-08', true],
    ] as [$lunes, $martes, $miercoles, $debeAsignar]) {
        foreach ([
            [$lunes, '08:00', '18:00'],
            [$martes, '08:00', '16:00'],
        ] as [$fecha, $inicio, $fin]) {
            $turno = Turno::create($datosTurno + [
                'fecha' => $fecha,
                'hora_inicio' => Carbon::parse(
                    "$fecha $inicio",
                    'Europe/Madrid'
                ),
                'hora_fin' => Carbon::parse(
                    "$fecha $fin",
                    'Europe/Madrid'
                ),
            ]);

            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $pedro->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // Miércoles: vacante de 8 horas.
        $vacante = Turno::create($datosTurno + [
            'fecha' => $miercoles,
            'hora_inicio' => Carbon::parse(
                "$miercoles 08:00",
                'Europe/Madrid'
            ),
            'hora_fin' => Carbon::parse(
                "$miercoles 16:00",
                'Europe/Madrid'
            ),
        ]);

        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            $miercoles,
            $miercoles
        );

        $existeAsignacion = Asignacion::where(
            'turno_id',
            $vacante->id
        )->where('empleado_id', $pedro->id)->exists();

        $this->assertSame(
            $debeAsignar,
            $existeAsignacion,
            "Resultado inesperado para el turno del $miercoles"
        );
    }
}

}
