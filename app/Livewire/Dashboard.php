<?php

namespace App\Livewire;

use App\Models\Asignacion;
use App\Models\Ausencia;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Incidencia;
use App\Models\MovimientoEconomico;
use App\Models\Proyecto;
use App\Models\RegistroHora;
use App\Models\Turno;
use App\Services\EconomicCalculationService;
use App\Services\RosterService;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $hoy = Carbon::now('Europe/Madrid');
        $inicioSemana = $hoy->copy()->startOfWeek();
        $finSemana = $hoy->copy()->endOfWeek();

        // Métricas de Planificación (Semana actual)
        $rosterService = new RosterService();
        $cuadranteSemanal = $rosterService->getCuadrante([
            'fecha_inicio' => $inicioSemana,
            'fecha_fin' => $finSemana,
        ]);

        $metricasPlanificacion = $cuadranteSemanal['estadisticas'];
        $conflictosPendientes = Asignacion::where('tiene_conflicto', true)
            ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
            ->with(['turno.instalacion', 'empleado'])
            ->get();

        // Métricas de RRHH
        $empleadosActivos = Empleado::where('activo', true)->count();
        $contratosVigentes = Contrato::where('fecha_inicio', '<=', $hoy->toDateString())
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy->toDateString());
            })->count();
        $ausenciasPendientes = Ausencia::where('estado', Ausencia::ESTADO_PENDIENTE)->count();
        $incidenciasAbiertas = Incidencia::where('estado', '!=', Incidencia::ESTADO_RESUELTA)->count();

        // Métricas de Control Económico
        $registrosPendientesValidar = RegistroHora::where('estado', RegistroHora::ESTADO_PENDIENTE)->count();

        // Cálculo económico del primer proyecto activo para la tarjeta resumen
        $proyectoActivo = Proyecto::where('estado', Proyecto::ESTADO_ACTIVO)->first();
        $informeEconomico = null;
        if ($proyectoActivo) {
            $econService = new EconomicCalculationService();
            $informeEconomico = $econService->calcularInformeProyecto($proyectoActivo);
        }

        // Datos específicos para el Empleado
        $misProximosTurnos = collect();
        $misHorasPendientes = 0;
        if ($user->employee_id) {
            $misProximosTurnos = Turno::whereHas('asignacionesActivas', function ($q) use ($user) {
                $q->where('empleado_id', $user->employee_id);
            })
            ->where('fecha', '>=', $hoy->toDateString())
            ->with(['instalacion', 'actividad'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->take(5)
            ->get();

            $misHorasPendientes = Asignacion::where('empleado_id', $user->employee_id)
                ->where('estado', Asignacion::ESTADO_CONFIRMADO)
                ->whereHas('turno', fn ($q) => $q->where('fecha', '<=', $hoy->toDateString()))
                ->doesntHave('registroHora')
                ->count();
        }

        return view('livewire.dashboard', [
            'metricasPlanificacion' => $metricasPlanificacion,
            'conflictosPendientes' => $conflictosPendientes,
            'empleadosActivos' => $empleadosActivos,
            'contratosVigentes' => $contratosVigentes,
            'ausenciasPendientes' => $ausenciasPendientes,
            'incidenciasAbiertas' => $incidenciasAbiertas,
            'registrosPendientesValidar' => $registrosPendientesValidar,
            'proyectoActivo' => $proyectoActivo,
            'informeEconomico' => $informeEconomico,
            'misProximosTurnos' => $misProximosTurnos,
            'misHorasPendientes' => $misHorasPendientes,
        ])->layout('layouts.app', ['title' => 'Panel de Control']);
    }
}
