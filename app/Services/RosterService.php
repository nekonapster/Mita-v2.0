<?php

namespace App\Services;

use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RosterService
{
    /**
     * Obtiene los turnos para la vista semanal o mensual aplicando filtros y calculando métricas.
     *
     * @param array{
     *   fecha_inicio: Carbon|string,
     *   fecha_fin: Carbon|string,
     *   cliente_id?: ?int,
     *   instalacion_id?: ?int,
     *   proyecto_id?: ?int,
     *   empleado_id?: ?int,
     *   categoria_id?: ?int,
     *   estado_cobertura?: ?string
     * } $filtros
     * @return array{
     *   turnos: Collection<Turno>,
     *   estadisticas: array{
     *     total: int,
     *     cubiertos: int,
     *     parciales: int,
     *     sin_cubrir: int,
     *     conflictos: int
     *   }
     * }
     */
    public function getCuadrante(array $filtros): array
    {
        $fechaInicio = Carbon::parse($filtros['fecha_inicio'])->toDateString();
        $fechaFin = Carbon::parse($filtros['fecha_fin'])->toDateString();

        $query = Turno::whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', Turno::ESTADO_CANCELADO)
            ->with([
                'instalacion.cliente',
                'actividad',
                'categoria',
                'proyecto',
                'asignacionesActivas.empleado.categoria',
            ])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if (! empty($filtros['cliente_id'])) {
            $query->whereHas('instalacion', fn ($q) => $q->where('cliente_id', $filtros['cliente_id']));
        }

        if (! empty($filtros['instalacion_id'])) {
            $query->where('instalacion_id', $filtros['instalacion_id']);
        }

        if (! empty($filtros['proyecto_id'])) {
            $query->where('proyecto_id', $filtros['proyecto_id']);
        }

        if (! empty($filtros['categoria_id'])) {
            $query->where('categoria_id', $filtros['categoria_id']);
        }

        if (! empty($filtros['empleado_id'])) {
            $query->whereHas('asignacionesActivas', fn ($q) => $q->where('empleado_id', $filtros['empleado_id']));
        }

        $todosTurnos = $query->get();

        $cubiertos = 0;
        $parciales = 0;
        $sinCubrir = 0;
        $conflictos = 0;

        foreach ($todosTurnos as $turno) {
            $cobertura = $turno->estadoCobertura();
            if ($cobertura === 'cubierto') {
                $cubiertos++;
            } elseif ($cobertura === 'parcialmente_cubierto') {
                $parciales++;
            } elseif ($cobertura === 'sin_cubrir') {
                $sinCubrir++;
            } elseif ($cobertura === 'con_conflicto') {
                $conflictos++;
            }
        }

        // Filtrar por estado de cobertura en memoria si se especifica
        $resultado = $todosTurnos;
        if (! empty($filtros['estado_cobertura'])) {
            $estadoFiltro = $filtros['estado_cobertura'];
            $resultado = $todosTurnos->filter(function ($t) use ($estadoFiltro) {
                return $t->estadoCobertura() === $estadoFiltro;
            })->values();
        }

        return [
            'turnos' => $resultado,
            'estadisticas' => [
                'total' => $todosTurnos->count(),
                'cubiertos' => $cubiertos,
                'parciales' => $parciales,
                'sin_cubrir' => $sinCubrir,
                'conflictos' => $conflictos,
            ],
        ];
    }
}
