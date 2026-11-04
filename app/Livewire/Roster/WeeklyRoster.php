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
use App\Models\Role;
use App\Services\AutomaticScheduleService;
use Illuminate\Validation\Rule;

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

    public function updatedClienteId(): void
    {
        // Al cambiar de cliente, invalidar las selecciones anteriores.
        $this->proyectoId = null;
        $this->instalacionId = null;
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
        $usuario = auth()->user();

        abort_unless(
            $usuario &&
                (
                    $usuario->isAdmin() ||
                    $usuario->hasRole('planner')
                ),
            403,
            'No tienes permisos para asignar empleados.'
        );
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
        $usuario = auth()->user();

        abort_unless(
            $usuario &&
                (
                    $usuario->isAdmin() ||
                    $usuario->hasRole('planner')
                ),
            403,
            'No tienes permisos para desasignar empleados.'
        );

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

    public function generarAutomaticamente(): void
    {
        $usuario = auth()->user();

        // Solo administradores y supervisores.
        if (
            !$usuario ||
            (
                !$usuario->isAdmin()
                && !$usuario->roles()
                    ->where('name', Role::PLANNER)
                    ->exists()
            )
        ) {
            abort(403, 'No tienes permisos para generar cuadrantes.');
        }

        // Es obligatorio seleccionar un proyecto.
        $this->validate([
            'proyectoId' => [
                'required',
                'integer',
                Rule::exists('proyectos', 'id')->where(
                    fn($query) => $this->clienteId
                        ? $query->where('cliente_id', $this->clienteId)
                        : $query
                ),
            ],

            'instalacionId' => [
                'nullable',
                'integer',
                Rule::exists('instalaciones', 'id')->where(
                    function ($query) {
                        $query->whereIn(
                            'id',
                            \Illuminate\Support\Facades\DB::table('instalacion_proyecto')
                                ->select('instalacion_id')
                                ->where('proyecto_id', $this->proyectoId)
                        );

                        if ($this->clienteId) {
                            $query->where('cliente_id', $this->clienteId);
                        }
                    }
                ),
            ],
        ], [
            'proyectoId.required' => 'Debes seleccionar un proyecto.',
            'proyectoId.exists' => 'El proyecto no pertenece al cliente seleccionado.',
            'instalacionId.exists' => 'La instalación no pertenece al proyecto seleccionado.',
        ]);

        // Determinar el período visible del cuadrante.
        $fecha = Carbon::parse($this->currentDate, 'Europe/Madrid');

        if ($this->viewMode === 'weekly') {
            $desde = $fecha->copy()->startOfWeek();
            $hasta = $fecha->copy()->endOfWeek();
        } else {
            $desde = $fecha->copy()->startOfMonth();
            $hasta = $fecha->copy()->endOfMonth();
        }

        // Recuperar el proyecto seleccionado.
        $proyecto = Proyecto::findOrFail($this->proyectoId);

        // Ejecutar el generador automático.
        app(AutomaticScheduleService::class)->generar(
            $proyecto,
            $desde->toDateString(),
            $hasta->toDateString(),
            $this->instalacionId
        );

        session()->flash(
            'success',
            'Generación automática del cuadrante completada.'
        );

        // Próximo paso:
        // Conectar AutomaticScheduleService.
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
            'instalaciones' => Instalacion::query()
                ->where('activo', true)

                // Filtrar por cliente, si está seleccionado.
                ->when(
                    $this->clienteId,
                    fn($query) => $query->where('cliente_id', $this->clienteId)
                )

                // Mostrar solo instalaciones asociadas al proyecto.
                ->when(
                    $this->proyectoId,
                    fn($query) => $query->whereHas(
                        'proyectos',
                        fn($proyectos) => $proyectos->whereKey($this->proyectoId)
                    )
                )

                ->orderBy('nombre')
                ->get(),
            'proyectos' => Proyecto::query()
                ->when(
                    $this->clienteId,
                    fn($query) => $query->where('cliente_id', $this->clienteId)
                )
                ->orderBy('nombre')
                ->get(),
            'empleados' => Empleado::where('activo', true)->orderBy('nombre')->get(),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Cuadrante Operativo']);
    }
}
