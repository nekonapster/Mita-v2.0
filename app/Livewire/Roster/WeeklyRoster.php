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
    public bool $confirmacionExcesoPendiente = false;
    public ?string $advertenciaExceso = null;
    public ?int $empleadoExcesoPendienteId = null;
    public ?int $turnoExcesoPendienteId = null;

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
        $this->confirmacionExcesoPendiente = false;
        $this->advertenciaExceso = null;
        $this->empleadoExcesoPendienteId = null;
        $this->turnoExcesoPendienteId = null;
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

        $inicio = \Carbon\Carbon::parse($this->turnoSeleccionado->hora_inicio);
        $fin = \Carbon\Carbon::parse($this->turnoSeleccionado->hora_fin);

        $zona = 'Europe/Madrid';
        $inicioLocal = $inicio->copy()->setTimezone($zona);
        $finLocal = $fin->copy()->setTimezone($zona);

        $lunes = $inicioLocal->copy()->startOfWeek();
        $siguienteLunes = $lunes->copy()->addWeek();

        $contrato = $empleado->contratos()
            ->whereDate('fecha_inicio', '<=', $inicioLocal->toDateString())
            ->where(function ($query) use ($finLocal) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $finLocal->toDateString());
            })
            ->first();

        if ($contrato) {
            $asignaciones = Asignacion::query()
                ->where('empleado_id', $empleado->id)
                ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
                ->whereHas('turno', function ($query) use ($lunes, $siguienteLunes) {
                    $query->where('estado', '!=', Turno::ESTADO_CANCELADO)
                        ->where('hora_inicio', '<', $siguienteLunes->copy()->utc())
                        ->where('hora_fin', '>', $lunes->copy()->utc());
                })
                ->with('turno')
                ->get();

            $horasAsignadas = $asignaciones->sum(function ($asignacion) use ($lunes, $siguienteLunes) {
                $desde = max(
                    $asignacion->turno->hora_inicio->getTimestamp(),
                    $lunes->getTimestamp()
                );

                $hasta = min(
                    $asignacion->turno->hora_fin->getTimestamp(),
                    $siguienteLunes->getTimestamp()
                );

                return max(0, $hasta - $desde) / 3600;
            });

            $horasNuevas = max(
                0,
                min($fin->getTimestamp(), $siguienteLunes->getTimestamp())
                    - max($inicio->getTimestamp(), $lunes->getTimestamp())
            ) / 3600;

            $limite = (float) $contrato->jornada_semanal_horas;
            $total = $horasAsignadas + $horasNuevas;

            if ($total > $limite) {
                $exceso = $total - $limite;

                $this->confirmacionExcesoPendiente = true;
                $this->empleadoExcesoPendienteId = $empleado->id;
                $this->turnoExcesoPendienteId = $this->turnoSeleccionado->id;
                $this->advertenciaExceso =
                    "Jornada contratada: {$limite} h. " .
                    "Horas previstas: {$total} h. " .
                    "Exceso: {$exceso} h.";

                return;
            }
        }

        $asignacion = Asignacion::firstOrNew([
            'turno_id' => $this->turnoSeleccionado->id,
            'empleado_id' => $empleado->id,
        ]);

        $asignacion->estado = Asignacion::ESTADO_CONFIRMADO;
        $asignacion->tiene_conflicto = false;
        $asignacion->save();

        $this->turnoSeleccionado->refresh();
        $this->turnoSeleccionado->load('asignacionesActivas.empleado.categoria');
        $this->nuevoEmpleadoId = null;
        session()->flash('success', "Empleado {$empleado->nombre_completo} asignado correctamente.");
    }


    public function confirmarAsignacionConExceso(): void
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

        if (
            ! $this->confirmacionExcesoPendiente ||
            ! $this->turnoSeleccionado ||
            ! $this->nuevoEmpleadoId ||
            $this->empleadoExcesoPendienteId !== (int) $this->nuevoEmpleadoId ||
            $this->turnoExcesoPendienteId !== (int) $this->turnoSeleccionado->id
        ) {
            $this->confirmacionExcesoPendiente = false;
            $this->advertenciaExceso = null;
            $this->empleadoExcesoPendienteId = null;
            $this->turnoExcesoPendienteId = null;

            return;
        }

        // Solo se puede confirmar una advertencia generada previamente.
        if (
            ! $this->confirmacionExcesoPendiente ||
            ! $this->turnoSeleccionado ||
            ! $this->nuevoEmpleadoId
        ) {
            return;
        }

        $empleado = Empleado::with([
            'categoria',
            'contratos',
            'ausencias',
        ])->findOrFail($this->nuevoEmpleadoId);

        // Volver a cargar el turno y repetir las validaciones.
        $turno = Turno::findOrFail($this->turnoSeleccionado->id);

        $validator = new ShiftAssignmentValidator();
        $resultado = $validator->validarAsignacion($turno, $empleado);

        if (! $resultado['valido']) {
            $this->erroresValidacion = $resultado['errores'];
            $this->confirmacionExcesoPendiente = false;
            $this->advertenciaExceso = null;
            return;
        }

        // Recalcular la jornada antes de aceptar la confirmación.
        $inicio = Carbon::parse($turno->hora_inicio);
        $fin = Carbon::parse($turno->hora_fin);

        $inicioLocal = $inicio->copy()->setTimezone('Europe/Madrid');
        $finLocal = $fin->copy()->setTimezone('Europe/Madrid');

        $lunes = $inicioLocal->copy()->startOfWeek();
        $siguienteLunes = $lunes->copy()->addWeek();

        $contrato = $empleado->contratos()
            ->whereDate('fecha_inicio', '<=', $inicioLocal->toDateString())
            ->where(function ($query) use ($finLocal) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $finLocal->toDateString());
            })
            ->first();

        // Si cambió la situación contractual, invalidar la confirmación.
        if (! $contrato) {
            $this->confirmacionExcesoPendiente = false;
            $this->advertenciaExceso = null;
            $this->erroresValidacion = [
                'La situación contractual ha cambiado. Revisa la asignación.',
            ];
            return;
        }

        $asignaciones = Asignacion::query()
            ->where('empleado_id', $empleado->id)
            ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
            ->whereHas('turno', function ($query) use ($lunes, $siguienteLunes) {
                $query->where('estado', '!=', Turno::ESTADO_CANCELADO)
                    ->where('hora_inicio', '<', $siguienteLunes->copy()->utc())
                    ->where('hora_fin', '>', $lunes->copy()->utc());
            })
            ->with('turno')
            ->get();

        $horasAsignadas = $asignaciones->sum(
            function ($asignacion) use ($lunes, $siguienteLunes) {
                $desde = max(
                    $asignacion->turno->hora_inicio->getTimestamp(),
                    $lunes->getTimestamp()
                );

                $hasta = min(
                    $asignacion->turno->hora_fin->getTimestamp(),
                    $siguienteLunes->getTimestamp()
                );

                return max(0, $hasta - $desde) / 3600;
            }
        );

        $horasNuevas = max(
            0,
            min($fin->getTimestamp(), $siguienteLunes->getTimestamp())
                - max($inicio->getTimestamp(), $lunes->getTimestamp())
        ) / 3600;

        $limite = (float) $contrato->jornada_semanal_horas;
        $total = $horasAsignadas + $horasNuevas;
        $exceso = $total - $limite;

        // Si el exceso cambió, solicitar una nueva confirmación.
        $nuevaAdvertencia =
            "Jornada contratada: {$limite} h. " .
            "Horas previstas: {$total} h. " .
            "Exceso: {$exceso} h.";

        if ($total <= $limite || $nuevaAdvertencia !== $this->advertenciaExceso) {
            $this->confirmacionExcesoPendiente = $total > $limite;
            $this->advertenciaExceso = $total > $limite
                ? $nuevaAdvertencia
                : null;

            $this->erroresValidacion = [
                'La jornada ha cambiado. Revisa las horas antes de confirmar.',
            ];

            return;
        }

        $asignacion = Asignacion::firstOrNew([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
        ]);

        $asignacion->estado = Asignacion::ESTADO_CONFIRMADO;
        $asignacion->tiene_conflicto = false;
        $asignacion->save();

        $this->turnoSeleccionado = $turno->fresh();
        $this->turnoSeleccionado->load(
            'asignacionesActivas.empleado.categoria'
        );

        $this->nuevoEmpleadoId = null;
        $this->confirmacionExcesoPendiente = false;
        $this->advertenciaExceso = null;
        $this->erroresValidacion = [];

        session()->flash(
            'success',
            "Empleado {$empleado->nombre_completo} asignado con exceso de jornada confirmado."
        );

        $this->empleadoExcesoPendienteId = null;
        $this->turnoExcesoPendienteId = null;
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

            // No desasignar si existen horas registradas.
            // Deben revisarse antes de cancelar la asignación.
            if ($asig->registroHora) {
                $this->erroresValidacion = [
                    'No se puede desasignar al empleado porque existen horas registradas. Deben revisarse previamente.',
                ];

                return;
            }

            // Conservar el historial sin ocupar una plaza.
            $asig->update([
                'estado' => Asignacion::ESTADO_CANCELADO,
            ]);

            $this->turnoSeleccionado->refresh();
            $this->turnoSeleccionado->load('asignacionesActivas.empleado.categoria');
            $this->erroresValidacion = [];
            session()->flash('success', 'Asignación cancelada correctamente.');
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
