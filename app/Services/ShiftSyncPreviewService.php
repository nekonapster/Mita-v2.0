<?php

namespace App\Services;

use App\Models\NecesidadServicio;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\Asignacion;
use Illuminate\Support\Facades\DB;
use App\Models\Ausencia;

class ShiftSyncPreviewService
{
    /**
     * Previsualiza los efectos de sincronizar o modificar los turnos asociados a una necesidad.
     *
     * @param array<string, mixed> $cambiosPropuestos
     * @return array{
     *   total_turnos: int,
     *   borradores_afectados: int,
     *   publicados_afectados: int,
     *   asignaciones_en_riesgo: int,
     *   detalles_publicados: Collection<Turno>
     * }
     */
    public function previsualizarImpacto(NecesidadServicio $necesidad, array $cambiosPropuestos = []): array
    {
        $turnos = Turno::where('necesidad_servicio_id', $necesidad->id)
            ->where('estado', '!=', Turno::ESTADO_CANCELADO)
            ->where('fecha', '>=', now()->toDateString())
            ->with(['asignacionesActivas.empleado', 'instalacion', 'actividad'])
            ->get();

        $borradores = $turnos->where('estado', Turno::ESTADO_BORRADOR);
        $publicados = $turnos->where('estado', Turno::ESTADO_PUBLICADO);

        $asignacionesEnRiesgo = 0;
        foreach ($publicados as $turno) {
            $asignacionesEnRiesgo += $turno->asignacionesActivas->count();
        }

        return [
            'total_turnos' => $turnos->count(),
            'borradores_afectados' => $borradores->count(),
            'publicados_afectados' => $publicados->count(),
            'asignaciones_en_riesgo' => $asignacionesEnRiesgo,
            'detalles_publicados' => $publicados,
        ];
    }

    /**
     * Aplica la sincronización a los turnos según la decisión confirmada.
     */
    public function sincronizarTurnos(
        NecesidadServicio $necesidad,
        bool $incluirPublicados = false
    ): array {
        $turnosQuery = Turno::where('necesidad_servicio_id', $necesidad->id)
            ->where('fecha', '>=', now()->toDateString());

        if (! $incluirPublicados) {
            $turnosQuery->where('estado', Turno::ESTADO_BORRADOR);
        } else {
            $turnosQuery->where('estado', '!=', Turno::ESTADO_CANCELADO);
        }

        return DB::transaction(function () use ($turnosQuery, $necesidad) {
            $turnos = $turnosQuery->get();
            $actualizados = 0;

            foreach ($turnos as $turno) {
                $currentDate = Carbon::parse($turno->fecha);
                $hIni = explode(':', $necesidad->hora_inicio);
                $hFin = explode(':', $necesidad->hora_fin);

                $dtInicio = Carbon::createFromTime((int)$hIni[0], (int)$hIni[1], 0, 'Europe/Madrid')
                    ->setDate($currentDate->year, $currentDate->month, $currentDate->day);
                $dtFin = Carbon::createFromTime((int)$hFin[0], (int)$hFin[1], 0, 'Europe/Madrid')
                    ->setDate($currentDate->year, $currentDate->month, $currentDate->day);

                if ($necesidad->cruza_medianoche || $dtFin->lte($dtInicio)) {
                    $dtFin->addDay();
                }

                // Comprobar que el nuevo horario no provoque solapamientos
                // con otros turnos asignados al mismo empleado.
                $empleadosAsignados = $turno->asignacionesActivas()
                    ->pluck('empleado_id');
                // Impedir que el nuevo horario se solape con ausencias aprobadas.
                if ($empleadosAsignados->isNotEmpty()) {
                    $ausenciaEnConflicto = Ausencia::query()
                        ->whereIn('empleado_id', $empleadosAsignados)
                        ->where('estado', Ausencia::ESTADO_APROBADA)
                        ->where('fecha_hora_inicio', '<', $dtFin)
                        ->where('fecha_hora_fin', '>', $dtInicio)
                        ->exists();

                    if ($ausenciaEnConflicto) {
                        throw new \DomainException(
                            "No se puede sincronizar el turno {$turno->id}: "
                                . 'el nuevo horario coincide con una ausencia aprobada '
                                . 'de uno de los empleados asignados.'
                        );
                    }
                }

                // Comprobar que todos los empleados asignados tengan
                // un contrato que cubra completamente el nuevo horario.
                if ($empleadosAsignados->isNotEmpty()) {
                    $asignaciones = $turno->asignacionesActivas()
                        ->with('empleado')
                        ->get();

                    foreach ($asignaciones as $asignacion) {
                        $empleado = $asignacion->empleado;

                        if (
                            ! $empleado ||
                            ! $empleado->tieneContratoVigenteEn($dtInicio, $dtFin)
                        ) {
                            throw new \DomainException(
                                "No se puede sincronizar el turno {$turno->id}: "
                                    . "el empleado {$asignacion->empleado_id} "
                                    . 'no tiene un contrato vigente durante todo el nuevo horario.'
                            );
                        }
                    }
                }


                // Comprobar que los empleados asignados sean compatibles
                // con la nueva categoría profesional del turno.
                if ($necesidad->categoria_id && $empleadosAsignados->isNotEmpty()) {
                    $empleadosIncompatibles = $turno->asignacionesActivas()
                        ->whereHas('empleado', function ($query) use ($necesidad) {
                            $query->where(function ($query) use ($necesidad) {
                                $query->where('categoria_id', '!=', $necesidad->categoria_id)
                                    ->orWhereNull('categoria_id');
                            });
                        })
                        ->exists();

                    if ($empleadosIncompatibles) {
                        throw new \DomainException(
                            "No se puede sincronizar el turno {$turno->id}: "
                                . 'hay empleados asignados cuya categoría profesional '
                                . 'no coincide con la nueva categoría requerida.'
                        );
                    }
                }
                // Impedir que la nueva capacidad sea inferior
                // al número de asignaciones activas existentes.
                $totalAsignados = $turno->asignacionesActivas()->count();

                if ($totalAsignados > $necesidad->plazas_necesarias) {
                    throw new \DomainException(
                        "No se puede sincronizar el turno {$turno->id}: "
                            . "hay {$totalAsignados} empleados asignados, "
                            . "pero la nueva capacidad es de {$necesidad->plazas_necesarias} plazas."
                    );
                }

                if ($empleadosAsignados->isNotEmpty()) {
                    $existeConflicto = Asignacion::query()
                        ->whereIn('empleado_id', $empleadosAsignados)
                        ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
                        ->where('turno_id', '!=', $turno->id)
                        ->whereHas('turno', function ($query) use ($dtInicio, $dtFin) {
                            $query->where('estado', '!=', Turno::ESTADO_CANCELADO)
                                ->where('hora_inicio', '<', $dtFin)
                                ->where('hora_fin', '>', $dtInicio);
                        })
                        ->exists();

                    if ($existeConflicto) {
                        throw new \DomainException(
                            "No se puede sincronizar el turno {$turno->id}: "
                                . 'el nuevo horario se solapa con otro turno asignado.'
                        );
                    }
                }

                $turno->update([
                    'hora_inicio' => $dtInicio,
                    'hora_fin' => $dtFin,
                    'plazas_necesarias' => $necesidad->plazas_necesarias,
                    'categoria_id' => $necesidad->categoria_id,
                    'actividad_id' => $necesidad->actividad_id,
                ]);

                $actualizados++;
            }

            return [
                'actualizados' => $actualizados,
            ];
        });
    }
}
