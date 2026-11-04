<?php

namespace Tests\Feature;

use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\NecesidadServicio;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Services\ShiftSyncPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Contrato;
use App\Models\Ausencia;

class ShiftSyncValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sincronizacion_no_permite_solapamiento_entre_turnos_asignados(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Martín',
            'apellidos' => 'Prueba',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Cavaleri',
            'activo' => true,
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscinas de verano',
            'fecha_inicio' => '2027-06-01',
        ]);

        $necesidad = NecesidadServicio::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha_inicio_vigencia' => '2027-06-01',
            'fecha_fin_vigencia' => '2027-06-30',
            'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'hora_inicio' => '12:15:00',
            'hora_fin' => '16:00:00',
            'cruza_medianoche' => false,
            'plazas_necesarias' => 1,
        ]);

        $turnoA = Turno::create([
            'necesidad_servicio_id' => $necesidad->id,
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2027-06-15',
            'hora_inicio' => '2027-06-15 12:15:00',
            'hora_fin' => '2027-06-15 16:00:00',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $turnoB = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2027-06-15',
            'hora_inicio' => '2027-06-15 17:00:00',
            'hora_fin' => '2027-06-15 20:45:00',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        foreach ([$turnoA, $turnoB] as $turno) {
            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $empleado->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // La necesidad cambia: ahora termina a las 18:00.
        $necesidad->update([
            'hora_fin' => '18:00:00',
        ]);

        $servicio = new ShiftSyncPreviewService();

        // La sincronización debe bloquear el conflicto.
        $this->expectException(\DomainException::class);

        try {
            $servicio->sincronizarTurnos($necesidad, true);
        } finally {
            // El horario original debe permanecer intacto.
            $this->assertEquals(
                '2027-06-15 16:00:00',
                $turnoA->fresh()->hora_fin->format('Y-m-d H:i:s')
            );
        }
    }
    public function test_sincronizacion_revierte_todos_los_turnos_si_uno_falla(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Martín',
            'apellidos' => 'Prueba',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Cavaleri',
            'activo' => true,
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscinas de verano',
            'fecha_inicio' => '2027-06-01',
        ]);

        $necesidad = NecesidadServicio::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha_inicio_vigencia' => '2027-06-01',
            'fecha_fin_vigencia' => '2027-06-30',
            'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'hora_inicio' => '12:15:00',
            'hora_fin' => '16:00:00',
            'cruza_medianoche' => false,
            'plazas_necesarias' => 1,
        ]);

        // Primer turno: se puede actualizar sin conflictos.
        $turnoA = Turno::create([
            'necesidad_servicio_id' => $necesidad->id,
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2027-06-15',
            'hora_inicio' => '2027-06-15 12:15:00',
            'hora_fin' => '2027-06-15 16:00:00',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Segundo turno: tendrá un conflicto al ampliar el horario.
        $turnoB = Turno::create([
            'necesidad_servicio_id' => $necesidad->id,
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2027-06-16',
            'hora_inicio' => '2027-06-16 12:15:00',
            'hora_fin' => '2027-06-16 16:00:00',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        // Otro turno del mismo empleado, de 17:00 a 20:45.
        $turnoC = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2027-06-16',
            'hora_inicio' => '2027-06-16 17:00:00',
            'hora_fin' => '2027-06-16 20:45:00',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        foreach ([$turnoB, $turnoC] as $turno) {
            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $empleado->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        // Se solicita ampliar ambos turnos hasta las 18:00.
        $necesidad->update([
            'hora_fin' => '18:00:00',
        ]);

        $servicio = new ShiftSyncPreviewService();

        $this->expectException(\DomainException::class);

        try {
            $servicio->sincronizarTurnos($necesidad, true);
        } finally {
            // Ningún turno debe quedar parcialmente actualizado.
            $this->assertEquals(
                '2027-06-15 16:00:00',
                $turnoA->fresh()->hora_fin->format('Y-m-d H:i:s')
            );

            $this->assertEquals(
                '2027-06-16 16:00:00',
                $turnoB->fresh()->hora_fin->format('Y-m-d H:i:s')
            );
        }
    }
    public function test_sincronizacion_rechaza_cambio_de_categoria_incompatible(): void
{
    $socorrista = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    $monitor = Categoria::create([
        'nombre' => 'Monitor de natación',
        'codigo' => 'MON',
    ]);

    $empleado = Empleado::create([
        'nombre' => 'Martín',
        'apellidos' => 'Prueba',
        'categoria_id' => $socorrista->id,
        'activo' => true,
    ]);

    $cliente = Cliente::create([
        'razon_social' => 'Ayuntamiento de Mairena',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Cavaleri',
        'activo' => true,
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscinas de verano',
        'fecha_inicio' => '2027-06-01',
    ]);

    $necesidad = NecesidadServicio::create([
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $socorrista->id,
        'fecha_inicio_vigencia' => '2027-06-01',
        'fecha_fin_vigencia' => '2027-06-30',
        'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
        'hora_inicio' => '12:15:00',
        'hora_fin' => '20:45:00',
        'cruza_medianoche' => false,
        'plazas_necesarias' => 1,
    ]);

    $turno = Turno::create([
        'necesidad_servicio_id' => $necesidad->id,
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $socorrista->id,
        'fecha' => '2027-06-15',
        'hora_inicio' => '2027-06-15 12:15:00',
        'hora_fin' => '2027-06-15 20:45:00',
        'plazas_necesarias' => 1,
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    Asignacion::create([
        'turno_id' => $turno->id,
        'empleado_id' => $empleado->id,
        'estado' => Asignacion::ESTADO_CONFIRMADO,
    ]);

    // Se cambia la categoría exigida por la necesidad.
    $necesidad->update([
        'categoria_id' => $monitor->id,
    ]);

    $servicio = new ShiftSyncPreviewService();

    $this->expectException(\DomainException::class);

    try {
        $servicio->sincronizarTurnos($necesidad, true);
    } finally {
        // El turno debe conservar su categoría original.
        $this->assertEquals(
            $socorrista->id,
            $turno->fresh()->categoria_id
        );

        // La asignación existente debe permanecer intacta.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }
}
public function test_sincronizacion_no_permite_reducir_plazas_por_debajo_de_asignaciones(): void
{
    $categoria = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    $cliente = Cliente::create([
        'razon_social' => 'Ayuntamiento de Mairena',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Cavaleri',
        'activo' => true,
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscinas de verano',
        'fecha_inicio' => '2027-06-01',
    ]);

    $necesidad = NecesidadServicio::create([
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha_inicio_vigencia' => '2027-06-01',
        'fecha_fin_vigencia' => '2027-06-30',
        'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
        'hora_inicio' => '12:15:00',
        'hora_fin' => '20:45:00',
        'cruza_medianoche' => false,
        'plazas_necesarias' => 3,
    ]);

    $turno = Turno::create([
        'necesidad_servicio_id' => $necesidad->id,
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha' => '2027-06-15',
        'hora_inicio' => '2027-06-15 12:15:00',
        'hora_fin' => '2027-06-15 20:45:00',
        'plazas_necesarias' => 3,
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    // Dos empleados asignados al mismo turno.
    foreach (['Martín', 'Andrés'] as $nombre) {
        $empleado = Empleado::create([
            'nombre' => $nombre,
            'apellidos' => 'Prueba',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }

    // Se intenta reducir la capacidad a una sola plaza.
    $necesidad->update([
        'plazas_necesarias' => 1,
    ]);

    $servicio = new ShiftSyncPreviewService();

    $this->expectException(\DomainException::class);

    try {
        $servicio->sincronizarTurnos($necesidad, true);
    } finally {
        // La capacidad original debe permanecer intacta.
        $this->assertEquals(
            3,
            $turno->fresh()->plazas_necesarias
        );

        // Las dos asignaciones deben conservarse.
        $this->assertEquals(
            2,
            Asignacion::where('turno_id', $turno->id)
                ->where('estado', Asignacion::ESTADO_CONFIRMADO)
                ->count()
        );
    }
}
public function test_sincronizacion_rechaza_turno_fuera_de_vigencia_contractual(): void
{
    $categoria = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    $empleado = Empleado::create([
        'nombre' => 'Martín',
        'apellidos' => 'Prueba',
        'categoria_id' => $categoria->id,
        'activo' => true,
    ]);

    Contrato::create([
        'empleado_id' => $empleado->id,
        'tipo_contrato' => 'Temporal',
        'fecha_inicio' => '2027-01-01',
        'fecha_fin' => '2027-06-15',
        'jornada_semanal_horas' => 40,
    ]);

    $cliente = Cliente::create([
        'razon_social' => 'Ayuntamiento de Mairena',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Cavaleri',
        'activo' => true,
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscinas de verano',
        'fecha_inicio' => '2027-06-01',
    ]);

    $necesidad = NecesidadServicio::create([
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha_inicio_vigencia' => '2027-06-01',
        'fecha_fin_vigencia' => '2027-06-30',
        'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
        'hora_inicio' => '18:00:00',
        'hora_fin' => '23:00:00',
        'cruza_medianoche' => false,
        'plazas_necesarias' => 1,
    ]);

    $turno = Turno::create([
        'necesidad_servicio_id' => $necesidad->id,
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha' => '2027-06-15',
        'hora_inicio' => '2027-06-15 18:00:00',
        'hora_fin' => '2027-06-15 23:00:00',
        'plazas_necesarias' => 1,
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    Asignacion::create([
        'turno_id' => $turno->id,
        'empleado_id' => $empleado->id,
        'estado' => Asignacion::ESTADO_CONFIRMADO,
    ]);

    // Se amplía el turno hasta las 02:00 del día siguiente.
    $necesidad->update([
        'hora_fin' => '02:00:00',
        'cruza_medianoche' => true,
    ]);

    $servicio = new ShiftSyncPreviewService();

    $this->expectException(\DomainException::class);

    try {
        $servicio->sincronizarTurnos($necesidad, true);
    } finally {
        // El turno debe conservar su horario original.
        $this->assertEquals(
            '2027-06-15 23:00:00',
            $turno->fresh()->hora_fin->format('Y-m-d H:i:s')
        );

        // La asignación original debe mantenerse.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }
}
public function test_sincronizacion_rechaza_conflicto_con_ausencia_aprobada(): void
{
    $categoria = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    $empleado = Empleado::create([
        'nombre' => 'Martín',
        'apellidos' => 'Prueba',
        'categoria_id' => $categoria->id,
        'activo' => true,
    ]);

    $cliente = Cliente::create([
        'razon_social' => 'Ayuntamiento de Mairena',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Cavaleri',
        'activo' => true,
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscinas de verano',
        'fecha_inicio' => '2027-06-01',
    ]);

    $necesidad = NecesidadServicio::create([
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha_inicio_vigencia' => '2027-06-01',
        'fecha_fin_vigencia' => '2027-06-30',
        'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
        'hora_inicio' => '12:15:00',
        'hora_fin' => '16:00:00',
        'cruza_medianoche' => false,
        'plazas_necesarias' => 1,
    ]);

    $turno = Turno::create([
        'necesidad_servicio_id' => $necesidad->id,
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha' => '2027-06-15',
        'hora_inicio' => '2027-06-15 12:15:00',
        'hora_fin' => '2027-06-15 16:00:00',
        'plazas_necesarias' => 1,
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    Asignacion::create([
        'turno_id' => $turno->id,
        'empleado_id' => $empleado->id,
        'estado' => Asignacion::ESTADO_CONFIRMADO,
    ]);

    Contrato::create([
        'empleado_id' => $empleado->id,
        'tipo_contrato' => 'Indefinido',
        'fecha_inicio' => '2027-01-01',
        'fecha_fin' => '2027-12-31',
        'jornada_semanal_horas' => 40,
    ]);

    $ausencia = Ausencia::create([
        'empleado_id' => $empleado->id,
        'tipo' => Ausencia::TIPO_PERMISO,
        'fecha_hora_inicio' => '2027-06-15 17:00:00',
        'fecha_hora_fin' => '2027-06-15 20:00:00',
        'estado' => Ausencia::ESTADO_APROBADA,
    ]);

    // Se amplía el turno hasta las 18:00.
    $necesidad->update([
        'hora_fin' => '18:00:00',
    ]);

    $servicio = new ShiftSyncPreviewService();

    $this->expectException(\DomainException::class);

    try {
        $servicio->sincronizarTurnos($necesidad, true);
    } finally {
        // El turno debe conservar el horario original.
        $this->assertEquals(
            '2027-06-15 16:00:00',
            $turno->fresh()->hora_fin->format('Y-m-d H:i:s')
        );

        // La ausencia aprobada debe permanecer intacta.
        $this->assertDatabaseHas('ausencias', [
            'id' => $ausencia->id,
            'empleado_id' => $empleado->id,
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        // La asignación original debe conservarse.
        $this->assertDatabaseHas('asignaciones', [
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);
    }
}
public function test_sincronizacion_permite_actualizar_turno_sin_conflictos(): void
{
    $categoria = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    $empleado = Empleado::create([
        'nombre' => 'Martín',
        'apellidos' => 'Prueba',
        'categoria_id' => $categoria->id,
        'activo' => true,
    ]);

    Contrato::create([
        'empleado_id' => $empleado->id,
        'tipo_contrato' => 'Indefinido',
        'fecha_inicio' => '2027-01-01',
        'fecha_fin' => '2027-12-31',
        'jornada_semanal_horas' => 40,
    ]);

    $cliente = Cliente::create([
        'razon_social' => 'Ayuntamiento de Mairena',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Cavaleri',
        'activo' => true,
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscinas de verano',
        'fecha_inicio' => '2027-06-01',
    ]);

    $necesidad = NecesidadServicio::create([
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha_inicio_vigencia' => '2027-06-01',
        'fecha_fin_vigencia' => '2027-06-30',
        'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
        'hora_inicio' => '12:15:00',
        'hora_fin' => '16:00:00',
        'cruza_medianoche' => false,
        'plazas_necesarias' => 1,
    ]);

    $turno = Turno::create([
        'necesidad_servicio_id' => $necesidad->id,
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha' => '2027-06-15',
        'hora_inicio' => '2027-06-15 12:15:00',
        'hora_fin' => '2027-06-15 16:00:00',
        'plazas_necesarias' => 1,
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    $asignacion = Asignacion::create([
        'turno_id' => $turno->id,
        'empleado_id' => $empleado->id,
        'estado' => Asignacion::ESTADO_CONFIRMADO,
    ]);

    $necesidad->update([
        'hora_fin' => '18:00:00',
    ]);

    $resultado = app(ShiftSyncPreviewService::class)
        ->sincronizarTurnos($necesidad, true);

    $this->assertEquals(1, $resultado['actualizados']);

    $this->assertEquals(
        '2027-06-15 18:00:00',
        $turno->fresh()->hora_fin->format('Y-m-d H:i:s')
    );

    $this->assertDatabaseHas('asignaciones', [
        'id' => $asignacion->id,
        'turno_id' => $turno->id,
        'empleado_id' => $empleado->id,
        'estado' => Asignacion::ESTADO_CONFIRMADO,
    ]);
}

}
