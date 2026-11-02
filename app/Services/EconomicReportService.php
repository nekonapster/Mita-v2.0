<?php

namespace App\Services;

use App\Models\CierreEconomico;
use App\Models\Proyecto;
use Carbon\Carbon;
use InvalidArgumentException;

class EconomicReportService
{
    public function __construct(
        private EconomicCalculationService $economicCalculationService
    ) {}

    public function obtenerInformeEconomico(
        Proyecto $proyecto,
        Carbon|string $desde,
        Carbon|string $hasta,
        ?int $instalacionId = null
    ): array {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();

        if ($inicio->greaterThan($fin)) {
            throw new InvalidArgumentException(
                'La fecha inicial no puede ser posterior a la final.'
            );
        }

        // Los cierres son del proyecto completo.
        // Las consultas por instalación siguen siendo dinámicas.
        if ($instalacionId !== null) {

            // Detectar cualquier mes cerrado incluido en la consulta.
            $existeCierre = CierreEconomico::query()
                ->where('proyecto_id', $proyecto->id)
                ->where(function ($query) use ($inicio, $fin) {
                    $query->where(function ($q) use ($inicio) {
                        $q->where('anio', '>', $inicio->year)
                            ->orWhere(function ($q) use ($inicio) {
                                $q->where('anio', $inicio->year)
                                    ->where('mes', '>=', $inicio->month);
                            });
                    })
                        ->where(function ($q) use ($fin) {
                            $q->where('anio', '<', $fin->year)
                                ->orWhere(function ($q) use ($fin) {
                                    $q->where('anio', $fin->year)
                                        ->where('mes', '<=', $fin->month);
                                });
                        });
                })
                ->exists();

            $informe = $this->calcularDinamico(
                $proyecto,
                $inicio,
                $fin,
                $instalacionId
            );

            $informe['advertencia_cierre'] = $existeCierre;

            return $informe;
        }

        $camposSumables = [
            'horas_previstas',
            'horas_realizadas',
            'coste_personal_previsto',
            'coste_personal_real',
            'ingresos_totales',
            'gastos_adicionales',
            'resultado_estimado',
        ];

        $resultado = array_fill_keys($camposSumables, 0.0);

        $resultado['coste_previsto_incompleto'] = false;
        $resultado['coste_real_incompleto'] = false;
        $resultado['desglose_explicativo'] = [];

        $mesesConsolidados = 0;
        $mesesDinamicos = 0;
        $advertenciaCierre = false;

        // Recorrer los meses incluidos en el intervalo.
        $cursor = $inicio->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($fin)) {

            $inicioTramo = $cursor->copy()->startOfMonth();
            $finTramo = $cursor->copy()->endOfMonth();

            // Respetar los límites solicitados.
            if ($inicioTramo->lessThan($inicio)) {
                $inicioTramo = $inicio->copy();
            }

            if ($finTramo->greaterThan($fin)) {
                $finTramo = $fin->copy();
            }

            // Solo utilizar cierres para meses completos.
            $mesCompleto =
                $inicioTramo->isSameDay($cursor->copy()->startOfMonth())
                && $finTramo->isSameDay($cursor->copy()->endOfMonth());

            $cierre = null;

            $cierreExistente = CierreEconomico::query()
                ->where('proyecto_id', $proyecto->id)
                ->where('anio', $cursor->year)
                ->where('mes', $cursor->month)
                ->first();

            if ($mesCompleto) {
                $cierre = $cierreExistente;
            } elseif ($cierreExistente) {
                // Consulta parcial de un período cerrado:
                // el cálculo es dinámico, no corresponde al cierre oficial.
                $advertenciaCierre = true;
            }

            if ($cierre) {
                $informeMes = $cierre->datos_consolidados;
                $mesesConsolidados++;
            } else {
                $informeMes = $this->economicCalculationService
                    ->calcularInformeProyecto(
                        $proyecto,
                        $inicioTramo,
                        $finTramo
                    );

                $mesesDinamicos++;
            }

            // Sumar exclusivamente indicadores numéricos.
            foreach ($camposSumables as $campo) {
                $resultado[$campo] += (float) ($informeMes[$campo] ?? 0);
            }

            // Combinar alertas.
            $resultado['coste_previsto_incompleto'] =
                $resultado['coste_previsto_incompleto']
                || ($informeMes['coste_previsto_incompleto'] ?? false);

            $resultado['coste_real_incompleto'] =
                $resultado['coste_real_incompleto']
                || ($informeMes['coste_real_incompleto'] ?? false);

            // Conservar las explicaciones sin duplicarlas.
            $resultado['desglose_explicativo'] = array_values(
                array_unique(
                    array_merge(
                        $resultado['desglose_explicativo'],
                        $informeMes['desglose_explicativo'] ?? []
                    )
                )
            );

            $cursor->addMonth();
        }

        // Redondear los indicadores numéricos.
        foreach ($camposSumables as $campo) {
            $resultado[$campo] = round($resultado[$campo], 2);
        }

        // Identificar el origen de los datos.
        if ($mesesConsolidados > 0 && $mesesDinamicos > 0) {
            $resultado['tipo_informe'] = 'mixto';
        } elseif ($mesesConsolidados > 0) {
            $resultado['tipo_informe'] = 'consolidado';
        } else {
            $resultado['tipo_informe'] = 'dinamico';
        }

        $resultado['advertencia_cierre'] = $advertenciaCierre;

        return $resultado;
    }

    private function calcularDinamico(
        Proyecto $proyecto,
        Carbon $inicio,
        Carbon $fin,
        ?int $instalacionId = null
    ): array {
        $informe = $this->economicCalculationService
            ->calcularInformeProyecto(
                $proyecto,
                $inicio,
                $fin,
                $instalacionId
            );

        return array_merge($informe, [
            'tipo_informe' => 'dinamico',
        ]);
    }
}
