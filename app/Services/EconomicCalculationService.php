<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\MovimientoEconomico;
use App\Models\Proyecto;
use App\Models\RegistroHora;
use App\Models\Turno;
use Carbon\Carbon;

class EconomicCalculationService
{
    /**
     * Calcula los informes e indicadores económicos de un proyecto para un período e instalación opcional.
     *
     * @return array{
     *   horas_previstas: float,
     *   horas_realizadas: float,
     *   coste_personal_previsto: float,
     *   coste_personal_real: float,
     *   coste_previsto_incompleto: bool,
     *   coste_real_incompleto: bool,
     *   ingresos_totales: float,
     *   gastos_adicionales: float,
     *   resultado_estimado: float,
     *   desglose_explicativo: list<string>
     * }
     */
    public function calcularInformeProyecto(
        Proyecto $proyecto,
        Carbon|string|null $desde = null,
        Carbon|string|null $hasta = null,
        ?int $instalacionId = null
    ): array {
        $desdeStr = $desde ? Carbon::parse($desde)->toDateString() : null;
        $hastaStr = $hasta ? Carbon::parse($hasta)->toDateString() : null;

        // 1. Filtrar turnos del proyecto
        $turnosQuery = Turno::where('proyecto_id', $proyecto->id)
            ->where('estado', '!=', Turno::ESTADO_CANCELADO);

        if ($desdeStr) {
            $turnosQuery->where('fecha', '>=', $desdeStr);
        }
        if ($hastaStr) {
            $turnosQuery->where('fecha', '<=', $hastaStr);
        }
        if ($instalacionId) {
            $turnosQuery->where('instalacion_id', $instalacionId);
        }

        $turnos = $turnosQuery->with(['asignacionesActivas.empleado', 'categoria'])->get();

        $horasPrevistas = 0.0;
        $costePersonalPrevisto = 0.0;
        $costePrevistoIncompleto = false;

        foreach ($turnos as $turno) {
            $duracion = $turno->duracionHoras();
            $asignaciones = $turno->asignacionesActivas;

            // Cada asignación activa suma horas previstas
            foreach ($asignaciones as $asignacion) {
                $horasPrevistas += $duracion;
                $empleado = $asignacion->empleado;

                if ($empleado) {
                    $tarifa = $empleado->tarifaEnFecha($turno->fecha);
                    if ($tarifa !== null) {
                        $costePersonalPrevisto += ($duracion * $tarifa);
                    } else {
                        $costePrevistoIncompleto = true;
                    }
                } else {
                    $costePrevistoIncompleto = true;
                }
            }
        }

        // 2. Horas reales y costes reales a partir de registros_horas validados
        $registrosQuery = RegistroHora::where('estado', RegistroHora::ESTADO_VALIDADO)
            ->whereHas('asignacion.turno', function ($q) use ($proyecto, $desdeStr, $hastaStr, $instalacionId) {
                $q->where('proyecto_id', $proyecto->id)
                    ->where('estado', '!=', Turno::ESTADO_CANCELADO);
                if ($desdeStr) {
                    $q->where('fecha', '>=', $desdeStr);
                }
                if ($hastaStr) {
                    $q->where('fecha', '<=', $hastaStr);
                }
                if ($instalacionId) {
                    $q->where('instalacion_id', $instalacionId);
                }
            })
            ->with(['asignacion.turno', 'asignacion.empleado']);

        $registrosValidados = $registrosQuery->get();

        $horasRealizadas = 0.0;
        $costePersonalReal = 0.0;
        $costeRealIncompleto = false;

        foreach ($registrosValidados as $reg) {
            $horas = (float) $reg->horas_computables;
            $horasRealizadas += $horas;

            $turno = $reg->asignacion->turno;
            $empleado = $reg->asignacion->empleado;

            if ($empleado && $turno) {
                $tarifa = $empleado->tarifaEnFecha($turno->fecha);
                if ($tarifa !== null) {
                    $costePersonalReal += ($horas * $tarifa);
                } else {
                    $costeRealIncompleto = true;
                }
            } else {
                $costeRealIncompleto = true;
            }
        }

        // 3. Movimientos económicos (ingresos y gastos adicionales)
        $movimientosQuery = MovimientoEconomico::where('proyecto_id', $proyecto->id);

        if ($desdeStr) {
            $movimientosQuery->where('fecha', '>=', $desdeStr);
        }
        if ($hastaStr) {
            $movimientosQuery->where('fecha', '<=', $hastaStr);
        }
        if ($instalacionId) {
            $movimientosQuery->where(function ($q) use ($instalacionId) {
                $q->where('instalacion_id', $instalacionId)
                    ->orWhereNull('instalacion_id');
            });
        }

        $movimientos = $movimientosQuery->get();

        $ingresosTotales = (float) $movimientos->where('tipo', MovimientoEconomico::TIPO_INGRESO)->sum('importe');
        $gastosAdicionales = (float) $movimientos->where('tipo', MovimientoEconomico::TIPO_GASTO)->sum('importe');

        // 4. Resultado interno estimado = Ingresos - Coste de Personal Real - Gastos Adicionales
        $resultadoEstimado = $ingresosTotales - $costePersonalReal - $gastosAdicionales;

        $desgloseExplicativo = [
            'Horas previstas: Suma de la duración programada de todas las asignaciones activas en turnos no cancelados.',
            'Coste previsto: Horas previstas multiplicadas por la tarifa horaria de coste del empleado (o su categoría) vigente en la fecha del turno.',
            'Horas realizadas: Suma de las horas computables (descontando descansos) de registros en estado validado.',
            'Coste real de personal: Horas realizadas validadas multiplicadas por la tarifa horaria vigente en la fecha del turno.',
            'Gastos adicionales: Gastos registrados para el proyecto (materiales, suministros, licencias), excluyendo costes de personal para evitar doble cómputo.',
            'Resultado interno: Ingresos totales registrados menos Coste real de personal menos Gastos adicionales.',
        ];

        return [
            'horas_previstas' => round($horasPrevistas, 2),
            'horas_realizadas' => round($horasRealizadas, 2),
            'coste_personal_previsto' => round($costePersonalPrevisto, 2),
            'coste_personal_real' => round($costePersonalReal, 2),
            'coste_previsto_incompleto' => $costePrevistoIncompleto,
            'coste_real_incompleto' => $costeRealIncompleto,
            'ingresos_totales' => round($ingresosTotales, 2),
            'gastos_adicionales' => round($gastosAdicionales, 2),
            'resultado_estimado' => round($resultadoEstimado, 2),
            'desglose_explicativo' => $desgloseExplicativo,
        ];
    }
}
