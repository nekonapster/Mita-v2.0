<?php

namespace App\Livewire\Roster;

use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Services\RosterService;
use App\Services\ShiftAssignmentValidator;
use Carbon\Carbon;
use Livewire\Component;

class WeeklyRoster extends Component
{
    public string $viewMode = 'weekly'; // weekly, monthly
    public string $currentDate = '';

    // Filtros
    public ?int $clienteId = null;
    public ?int $instalacionId = null;
    public ?int $proyectoId = null;
    public ?int $empleadoId = null;
    public ?int $categoriaId = null;
    public ?string $estadoCobertura = null;

    // Modal de Gestión de Turno y Asignación
    public bool $modalTurnoOpen = false;
    public ?Turno $turnoSeleccionado = null;
    public ?int $nuevoEmpleadoId = null;
    public array $erroresValidacion = [];

    public function mount(): void
    {
        $this->currentDate = now('Europe/Madrid')->toDateString();
    }

    public function semanaAnterior(): void
    {
        $this->currentDate = Carbon::parse($this->currentDate)->subWeek()->toDateString();
    }

    public function semanaSiguiente(): void
    {
        $this->currentDate = Carbon::parse($this->currentDate)->addWeek()->toDateString();
    }

    public function mesAnterior(): void
    {
        $this->currentDate = Carbon::parse($this->currentDate)->subMonth()->toDateString();
    }

    public function mesSiguiente(): void
    {
        $this->currentDate = Carbon::parse($this->currentDate)->addMonth()->toDateString();
    }

    public function irAHoy(): void
    {
        $this->currentDate = now('Europe/Madrid')->toDateString();
    }

    public function abrirModalTurno(int $turnoId): void
    {
        $this->turnoSeleccionado = Turno::with([
            'instalacion.cliente',
            'proyecto',
            'actividad',
            'categoria',
            'asignacionesActivas.empleado.categoria',
        ])->findOrFail($turnoId);

        $this->nuevoEmpleadoId = null;
        $this->erroresValidacion = [];
        $this->modalTurnoOpen = true;
    }

    public function asignarEmpleado(): void
    {
        $this->erroresValidacion = [];

        if (! $this->nuevoEmpleadoId) {
            $this->erroresValidacion = ['Debes seleccionar un empleado para asignar.'];
            return;
        }

        $empleado = Empleado::with(['categoria', 'contratos', 'ausencias'])->findOrFail($this->nuevoEmpleadoId);
        $validator = new ShiftAssignmentValidator();
        $resultado = $validator->validarAsignacion($this->turnoSeleccionado, $empleado);

        if (! $resultado['valido']) {
            $this->erroresValidacion = $resultado['errores'];
            return;
        }

        Asignacion::create([
            'turno_id' => $this->turnoSeleccionado->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
            'tiene_conflicto' => false,
        ]);

        $this->turnoSeleccionado->refresh();
        $this->turnoSeleccionado->load('asignacionesActivas.empleado.categoria');
        $this->nuevoEmpleadoId = null;
        session()->flash('success', "Empleado {$empleado->nombre_completo} asignado correctamente.");
    }

    public function desasignarEmpleado(int $asignacionId): void
    {
        try {
            $asig = Asignacion::with('registroHora')->findOrFail($asignacionId);

            if ($asig->registroHora) {
                if ($asig->registroHora->estado === 'validado') {
                    $this->erroresValidacion = ['No se puede desasignar a este empleado porque el turno ya cuenta con horas reales validadas.'];
                    return;
                }
                $asig->registroHora->delete();
            }

            $asig->delete();

            $this->turnoSeleccionado->refresh();
            $this->turnoSeleccionado->load('asignacionesActivas.empleado.categoria');
            $this->erroresValidacion = [];
            session()->flash('success', 'Asignación eliminada correctamente.');
        } catch (\Throwable $e) {
            $this->erroresValidacion = ['Error al eliminar la asignación: ' . $e->getMessage()];
        }
    }

    public function render()
    {
        $fechaRef = Carbon::parse($this->currentDate, 'Europe/Madrid');

        if ($this->viewMode === 'weekly') {
            $fechaInicio = $fechaRef->copy()->startOfWeek();
            $fechaFin = $fechaRef->copy()->endOfWeek();
        } else {
            $fechaInicio = $fechaRef->copy()->startOfMonth();
            $fechaFin = $fechaRef->copy()->endOfMonth();
        }

        $rosterService = new RosterService();
        $datosCuadrante = $rosterService->getCuadrante([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'cliente_id' => $this->clienteId,
            'instalacion_id' => $this->instalacionId,
            'proyecto_id' => $this->proyectoId,
            'empleado_id' => $this->empleadoId,
            'categoria_id' => $this->categoriaId,
            'estado_cobertura' => $this->estadoCobertura,
        ]);

        $turnosPorFecha = $datosCuadrante['turnos']->groupBy(function ($t) {
            return $t->fecha->toDateString();
        });

        // Generar días del período
        $diasPeriodo = collect();
        $curr = $fechaInicio->copy();
        while ($curr->lte($fechaFin)) {
            $diasPeriodo->push($curr->copy());
            $curr->addDay();
        }

        return view('livewire.roster.weekly-roster', [
            'diasPeriodo' => $diasPeriodo,
            'turnosPorFecha' => $turnosPorFecha,
            'estadisticas' => $datosCuadrante['estadisticas'],
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'clientes' => Cliente::where('activo', true)->orderBy('razon_social')->get(),
            'instalaciones' => Instalacion::where('activo', true)->orderBy('nombre')->get(),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
            'empleados' => Empleado::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Cuadrante Operativo']);
    }
}
