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
}
