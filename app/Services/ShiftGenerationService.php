<?php

namespace App\Services;

use App\Models\NecesidadServicio;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftGenerationService
{
    /**
     * Genera los turnos para una necesidad de servicio dentro de un rango de fechas.
     * Evita duplicados y maneja turnos que cruzan la medianoche y zona horaria Europe/Madrid.
     *
     * @return array{generados: int, omitidos: int, turnos: Collection<Turno>}
     */
    public function generarTurnos(
        NecesidadServicio $necesidad,
        Carbon|string $fechaDesde,
        Carbon|string $fechaHasta,
        string $estadoInicial = Turno::ESTADO_BORRADOR
    ): array {
        $desde = Carbon::parse($fechaDesde, 'Europe/Madrid')->startOfDay();
        $hasta = Carbon::parse($fechaHasta, 'Europe/Madrid')->endOfDay();

        // Limitar dentro del rango de vigencia de la necesidad
        $vigenciaInicio = Carbon::parse($necesidad->fecha_inicio_vigencia, 'Europe/Madrid')->startOfDay();
        $vigenciaFin = Carbon::parse($necesidad->fecha_fin_vigencia, 'Europe/Madrid')->endOfDay();

        $inicioEfectivo = $desde->max($vigenciaInicio);
        $finEfectivo = $hasta->min($vigenciaFin);

        $generados = 0;
        $omitidos = 0;
        $turnosCreados = collect();

        if ($inicioEfectivo->gt($finEfectivo)) {
            return [
                'generados' => 0,
                'omitidos' => 0,
                'turnos' => $turnosCreados,
            ];
        }

        $diasConfigurados = (array) $necesidad->dias_semana; // e.g. [1, 2, 3, 4, 5] (ISO: 1 = Lunes, 7 = Domingo)

        $current = $inicioEfectivo->copy();
        while ($current->lte($finEfectivo)) {
            $diaSemanaIso = $current->isoFormat('E'); // 1 a 7

            if (in_array((int) $diaSemanaIso, array_map('intval', $diasConfigurados), true)) {
                $fechaBase = $current->toDateString();

                // Construir fecha/hora inicio
                $horaInicioParts = explode(':', $necesidad->hora_inicio);
                $horaFinParts = explode(':', $necesidad->hora_fin);

                $dtInicio = Carbon::createFromTime(
                    (int) $horaInicioParts[0],
                    (int) $horaInicioParts[1],
                    (int) ($horaInicioParts[2] ?? 0),
                    'Europe/Madrid'
                )->setDate($current->year, $current->month, $current->day);

                $dtFin = Carbon::createFromTime(
                    (int) $horaFinParts[0],
                    (int) $horaFinParts[1],
                    (int) ($horaFinParts[2] ?? 0),
                    'Europe/Madrid'
                )->setDate($current->year, $current->month, $current->day);

                // Si cruza la medianoche o la hora de fin es menor/igual a la de inicio, pasa al día siguiente
                if ($necesidad->cruza_medianoche || $dtFin->lte($dtInicio)) {
                    $dtFin->addDay();
                }

                // Comprobar si ya existe un turno para esta necesidad en esta fecha para evitar duplicados
                $existe = Turno::where('necesidad_servicio_id', $necesidad->id)
                    ->whereDate('fecha', $fechaBase)
                    ->exists();

                if ($existe) {
                    $omitidos++;
                } else {
                    $turno = Turno::create([
                        'necesidad_servicio_id' => $necesidad->id,
                        'proyecto_id' => $necesidad->proyecto_id,
                        'instalacion_id' => $necesidad->instalacion_id,
                        'actividad_id' => $necesidad->actividad_id,
                        'categoria_id' => $necesidad->categoria_id,
                        'fecha' => $fechaBase,
                        'hora_inicio' => $dtInicio,
                        'hora_fin' => $dtFin,
                        'plazas_necesarias' => $necesidad->plazas_necesarias,
                        'estado' => $estadoInicial,
                        'observaciones' => $necesidad->observaciones,
                    ]);

                    $turnosCreados->push($turno);
                    $generados++;
                }
            }

            $current->addDay();
        }

        return [
            'generados' => $generados,
            'omitidos' => $omitidos,
            'turnos' => $turnosCreados,
        ];
    }
}
