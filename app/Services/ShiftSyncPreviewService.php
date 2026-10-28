<?php

namespace App\Services;

use App\Models\NecesidadServicio;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
    }
}
