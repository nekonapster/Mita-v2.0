<?php

namespace App\Livewire\Portal;

use App\Models\Asignacion;
use App\Models\Ausencia;
use App\Models\RegistroHora;
use App\Models\Turno;
use Carbon\Carbon;
use Livewire\Component;

class EmployeePortal extends Component
{
    public string $activeTab = 'turnos'; // turnos, imputar, ausencias

    // Modal Imputar Horas
    public bool $modalImputarOpen = false;
    public ?int $asignacionSeleccionadaId = null;
    public ?Turno $turnoParaImputar = null;
    public string $inicio_real = '';
    public string $fin_real = '';
    public int $descanso_minutos = 0;
    public string $observaciones_horas = '';

    // Modal Solicitar Ausencia
    public bool $modalAusenciaOpen = false;
    public string $tipo_ausencia = 'vacaciones';
    public string $ausencia_inicio = '';
    public string $ausencia_fin = '';
    public string $motivo_ausencia = '';

    public function mount(): void
    {
        $this->ausencia_inicio = now()->format('Y-m-d\T09:00');
        $this->ausencia_fin = now()->addDays(1)->format('Y-m-d\T18:00');
    }

    public function abrirModalImputar(int $asignacionId): void
    {
        $asig = Asignacion::with('turno.instalacion')->findOrFail($asignacionId);

        // Impedir consultar asignaciones de otros empleados.
        $usuario = auth()->user();

        abort_unless(
            $usuario?->employee_id !== null &&
                (int) $asig->empleado_id === (int) $usuario->employee_id,
            403
        );

        abort_if(
            $asig->turno?->estado === Turno::ESTADO_CANCELADO ||
                $asig->estado === Asignacion::ESTADO_CANCELADO,
            403,
            'No se pueden imputar horas en turnos o asignaciones canceladas.'
        );


        $this->asignacionSeleccionadaId = $asig->id;
        $this->turnoParaImputar = $asig->turno;

        $registroExistente = $asig->registroHora;
        if ($registroExistente) {
            $this->inicio_real = Carbon::parse($registroExistente->inicio_real)->format('Y-m-d\TH:i');
            $this->fin_real = Carbon::parse($registroExistente->fin_real)->format('Y-m-d\TH:i');
            $this->descanso_minutos = $registroExistente->descanso_minutos;
            $this->observaciones_horas = $registroExistente->observaciones ?? '';
        } else {
            $this->inicio_real = Carbon::parse($asig->turno->hora_inicio)->format('Y-m-d\TH:i');
            $this->fin_real = Carbon::parse($asig->turno->hora_fin)->format('Y-m-d\TH:i');
            $this->descanso_minutos = 0;
            $this->observaciones_horas = '';
        }

        $this->modalImputarOpen = true;
    }

    public function guardarImputacion(): void
    {

        // Obtener la asignación que se intenta modificar.
        $asignacion = Asignacion::findOrFail(
            $this->asignacionSeleccionadaId
        );

        // Comprobar que pertenece al empleado autenticado.
        $usuario = auth()->user();

        abort_unless(
            $usuario?->employee_id !== null &&
                (int) $asignacion->empleado_id === (int) $usuario->employee_id,
            403
        );

        // Impedir imputaciones en turnos o asignaciones canceladas.
        abort_if(
            $asignacion->turno?->estado === Turno::ESTADO_CANCELADO ||
                $asignacion->estado === Asignacion::ESTADO_CANCELADO,
            403,
            'No se pueden registrar horas en turnos o asignaciones canceladas.'
        );

        // Buscar un registro de horas existente.
        $registroExistente = RegistroHora::where(
            'asignacion_id',
            $asignacion->id
        )->first();

        // Comprobar los permisos correspondientes.
        if ($registroExistente) {

            // Si existe, comprobar permiso para modificarlo.
            \Illuminate\Support\Facades\Gate::authorize(
                'update',
                $registroExistente
            );
        } else {

            // Si no existe, comprobar permiso para crearlo.
            \Illuminate\Support\Facades\Gate::authorize(
                'create',
                RegistroHora::class
            );
        }
        $this->validate([
            'inicio_real' => 'required|date',
            'fin_real' => 'required|date|after_or_equal:inicio_real',
            'descanso_minutos' => 'required|integer|min:0',
            'observaciones_horas' => 'nullable|string|max:500',
        ]);

        $horasComputables = RegistroHora::calcularHoras(
            $this->inicio_real,
            $this->fin_real,
            $this->descanso_minutos
        );

        RegistroHora::updateOrCreate(
            ['asignacion_id' => $this->asignacionSeleccionadaId],
            [
                'inicio_real' => $this->inicio_real,
                'fin_real' => $this->fin_real,
                'descanso_minutos' => $this->descanso_minutos,
                'horas_computables' => $horasComputables,
                'observaciones' => $this->observaciones_horas ?: null,
                'estado' => RegistroHora::ESTADO_PENDIENTE,
                'modificado_por' => auth()->id(),
            ]
        );

        $this->modalImputarOpen = false;
        session()->flash('success', "Parte de horas registrado ({$horasComputables} h computables). Pendiente de validación por tu supervisor.");
    }

    public function solicitarAusencia(): void
    {
        $user = auth()->user();
        if (! $user->employee_id) {
            session()->flash('error', 'Tu usuario no tiene una ficha de empleado asociada.');
            return;
        }

        $this->validate([
            'tipo_ausencia' => 'required|in:vacaciones,baja,permiso,otro',
            'ausencia_inicio' => 'required|date',
            'ausencia_fin' => 'required|date|after_or_equal:ausencia_inicio',
            'motivo_ausencia' => 'nullable|string|max:500',
        ]);

        Ausencia::create([
            'empleado_id' => $user->employee_id,
            'tipo' => $this->tipo_ausencia,
            'fecha_hora_inicio' => $this->ausencia_inicio,
            'fecha_hora_fin' => $this->ausencia_fin,
            'estado' => Ausencia::ESTADO_PENDIENTE,
            'observaciones' => $this->motivo_ausencia ?: null,
        ]);

        $this->modalAusenciaOpen = false;
        $this->reset(['motivo_ausencia']);
        session()->flash('success', 'Solicitud de ausencia enviada a Recursos Humanos.');
    }

    public function render()
    {
        $user = auth()->user();
        $empleadoId = $user->employee_id;

        $turnosFuturos = collect();
        $turnosParaImputar = collect();
        $misAusencias = collect();

        if ($empleadoId) {
            // Próximos turnos (futuros o de hoy)
            $turnosFuturos = Asignacion::where('empleado_id', $empleadoId)
                ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
                ->whereHas('turno', fn($q) => $q->where('fecha', '>=', now()->toDateString())->where('estado', '!=', Turno::ESTADO_CANCELADO))
                ->with(['turno.instalacion', 'turno.actividad', 'registroHora'])
                ->get()
                ->sortBy(fn($a) => $a->turno->hora_inicio);

            // Turnos pasados o de hoy para imputar
            $turnosParaImputar = Asignacion::where('empleado_id', $empleadoId)
                ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
                ->whereHas('turno', fn($q) => $q->where('fecha', '<=', now()->toDateString())->where('estado', '!=', Turno::ESTADO_CANCELADO))
                ->with(['turno.instalacion', 'turno.actividad', 'registroHora'])
                ->get()
                ->sortByDesc(fn($a) => $a->turno->hora_inicio);

            $misAusencias = Ausencia::where('empleado_id', $empleadoId)
                ->orderBy('fecha_hora_inicio', 'desc')
                ->get();
        }

        return view('livewire.portal.employee-portal', [
            'turnosFuturos' => $turnosFuturos,
            'turnosParaImputar' => $turnosParaImputar,
            'misAusencias' => $misAusencias,
            'empleado' => $user->employee,
        ])->layout('layouts.app', ['title' => 'Portal del Empleado']);
    }
}
