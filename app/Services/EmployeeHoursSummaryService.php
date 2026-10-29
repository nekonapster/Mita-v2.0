<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Empleado;
use App\Models\Turno;
use Carbon\Carbon;

class EmployeeHoursSummaryService
{
    /**
     * Calcula las horas individuales de un empleado
     * dentro de un período determinado.
     */
    public function calcular(
        Empleado $empleado,
        string $desde,
        string $hasta
    ): array {

        $desde = Carbon::parse($desde)->toDateString();
        $hasta = Carbon::parse($hasta)->toDateString();

        // Obtener todas las asignaciones del período.
        $asignaciones = $empleado->asignaciones()
            ->whereHas('turno', function ($query) use ($desde, $hasta) {
                $query->whereDate('fecha', '>=', $desde)
                      ->whereDate('fecha', '<=', $hasta);
            })
            ->with(['turno', 'registroHora'])
            ->get();

        // Inicializar contadores.
        $horasPlanificadas = 0;
        $horasRealizadas = 0;
        $horasPendientes = 0;
        $horasCanceladas = 0;
        $horasBorrador = 0;

        foreach ($asignaciones as $asignacion) {

            $turno = $asignacion->turno;
            $registro = $asignacion->registroHora;

            if (!$turno) {
                continue;
            }

            $duracion = $turno->duracionHoras();

            // 1. Turnos o asignaciones canceladas.
            if (
                $turno->estado === Turno::ESTADO_CANCELADO ||
                $asignacion->estado === Asignacion::ESTADO_CANCELADO
            ) {
                $horasCanceladas += $duracion;
                continue;
            }

            // 2. Turnos todavía en borrador.
            if ($turno->estado === Turno::ESTADO_BORRADOR) {
                $horasBorrador += $duracion;
                continue;
            }

            // 3. Horas planificadas vigentes.
            if ($turno->estado === Turno::ESTADO_PUBLICADO) {

                $horasPlanificadas += $duracion;

                if ($registro) {

                    // Horas validadas.
                    if (
                        $registro->estado ===
                        \App\Models\RegistroHora::ESTADO_VALIDADO
                    ) {
                        $horasRealizadas +=
                            (float) $registro->horas_computables;
                    }

                    // Horas pendientes de revisión.
                    if (
                        $registro->estado ===
                        \App\Models\RegistroHora::ESTADO_PENDIENTE
                    ) {
                        $horasPendientes +=
                            (float) $registro->horas_computables;
                    }
                }
            }
        }

        return [
            'horas_planificadas' => round($horasPlanificadas, 2),
            'horas_realizadas'   => round($horasRealizadas, 2),
            'horas_pendientes'   => round($horasPendientes, 2),
            'horas_canceladas'   => round($horasCanceladas, 2),
            'horas_borrador'     => round($horasBorrador, 2),
        ];
    }
}