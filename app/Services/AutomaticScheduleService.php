<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Empleado;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Models\Contrato;
use Carbon\Carbon;

class AutomaticScheduleService
{
    public function __construct(
        private ShiftAssignmentValidator $validator
    ) {}

    public function generar(
        Proyecto $proyecto,
        string $fechaDesde,
        string $fechaHasta,
        ?int $instalacionId = null

    ): void {

        // Trabajar únicamente sobre turnos publicados del proyecto.
        $turnos = Turno::query()
            ->where('proyecto_id', $proyecto->id)
            ->where('estado', Turno::ESTADO_PUBLICADO)
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->when(
                $instalacionId !== null,
                fn($query) => $query->where('instalacion_id', $instalacionId)
            )
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        // Horas ya asignadas durante el período, en todas las instalaciones.
        // No contar asignaciones ni turnos cancelados.
        $horasAsignadas = Asignacion::query()
            ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
            ->whereHas('turno', function ($query) use ($fechaDesde, $fechaHasta) {
                $query->where('estado', '!=', Turno::ESTADO_CANCELADO)
                    ->whereDate('fecha', '>=', $fechaDesde)
                    ->whereDate('fecha', '<=', $fechaHasta);
            })
            ->with('turno')
            ->get()
            ->groupBy('empleado_id')
            ->map(fn($asignaciones) => $asignaciones->sum(
                fn($asignacion) => $asignacion->turno->duracionHoras()
            ))
            ->all();

        foreach ($turnos as $turno) {

            // Nunca superar las plazas necesarias.
            $plazasLibres = $turno->plazasDisponibles();

            if ($plazasLibres === 0) {
                continue;
            }

            // Buscar trabajadores de la categoría necesaria.
            $empleados = Empleado::query()
                ->where('activo', true)
                ->where('categoria_id', $turno->categoria_id)
                ->get()
                ->sortBy(fn($empleado) => [
                    $horasAsignadas[$empleado->id] ?? 0,
                    $empleado->id,
                ]);

            foreach ($empleados as $empleado) {

                if ($plazasLibres === 0) {
                    break;
                }

                // Evitar asignar dos veces al mismo trabajador.
                if ($turno->asignacionesActivas()
                    ->where('empleado_id', $empleado->id)
                    ->exists()
                ) {
                    continue;
                }

                // Reutilizar las validaciones laborales existentes.
                $validacion = $this->validator->validarAsignacion(
                    $turno,
                    $empleado
                );

                if (!$validacion['valido']) {
                    continue;
                }

                // No superar las horas semanales contratadas.
                if (!$this->respetaJornadaSemanal($empleado, $turno)) {
                    continue;
                }
                Asignacion::create([
                    'turno_id' => $turno->id,
                    'empleado_id' => $empleado->id,
                    'estado' => Asignacion::ESTADO_ASIGNADO,
                    'tiene_conflicto' => false,
                ]);

                $horasAsignadas[$empleado->id] =
                    ($horasAsignadas[$empleado->id] ?? 0)
                    + $turno->duracionHoras();

                $plazasLibres--;
            }
        }
    }

    private function respetaJornadaSemanal(
        Empleado $empleado,
        Turno $turno
    ): bool {
        $inicio = Carbon::parse($turno->hora_inicio);
        $fin = Carbon::parse($turno->hora_fin);

        // Obtener el contrato que cubre completamente el turno.
        $contrato = $empleado->contratos()
            ->whereDate('fecha_inicio', '<=', $inicio->toDateString())
            ->where(function ($query) use ($fin) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fin->toDateString());
            })
            ->first();

        if (!$contrato) {
            return false;
        }

        $limite = (float) $contrato->jornada_semanal_horas;


        // Los límites semanales se calculan en hora española.
        $zona = 'Europe/Madrid';

        $inicioLocal = $inicio->copy()->setTimezone($zona);
        $finLocal = $fin->copy()->setTimezone($zona);

        // Revisar cada semana que atraviesa el nuevo turno.
        $lunes = $inicioLocal->copy()->startOfWeek();

        while ($lunes->lessThan($finLocal)) {
            $siguienteLunes = $lunes->copy()->addWeek();

            // Consultar la base de datos utilizando límites UTC.
            $desdeUTC = $lunes->copy()->utc();
            $hastaUTC = $siguienteLunes->copy()->utc();

            $asignaciones = Asignacion::query()
                ->where('empleado_id', $empleado->id)
                ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
                ->whereHas('turno', function ($query) use (
                    $desdeUTC,
                    $hastaUTC
                ) {
                    $query
                        ->where('estado', '!=', Turno::ESTADO_CANCELADO)
                        ->where('hora_inicio', '<', $hastaUTC)
                        ->where('hora_fin', '>', $desdeUTC);
                })
                ->with('turno')
                ->get();

            $horasAsignadas = $asignaciones->sum(
                function ($asignacion) use ($lunes, $siguienteLunes) {
                    $inicioTurno = Carbon::parse(
                        $asignacion->turno->hora_inicio
                    )->setTimezone('Europe/Madrid');

                    $finTurno = Carbon::parse(
                        $asignacion->turno->hora_fin
                    )->setTimezone('Europe/Madrid');

                    $desde = max(
                        $inicioTurno->getTimestamp(),
                        $lunes->getTimestamp()
                    );

                    $hasta = min(
                        $finTurno->getTimestamp(),
                        $siguienteLunes->getTimestamp()
                    );

                    return max(0, $hasta - $desde) / 3600;
                }
            );

            // Horas del nuevo turno correspondientes a esta semana.
            $desdeNuevo = max(
                $inicioLocal->getTimestamp(),
                $lunes->getTimestamp()
            );

            $hastaNuevo = min(
                $finLocal->getTimestamp(),
                $siguienteLunes->getTimestamp()
            );

            $horasNuevas = max(
                0,
                $hastaNuevo - $desdeNuevo
            ) / 3600;

            if ($horasAsignadas + $horasNuevas > $limite) {
                return false;
            }

            $lunes = $siguienteLunes;
        }

        return true;
    }
}
