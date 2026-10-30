<?php

namespace App\Livewire\Rrhh;

use App\Models\Ausencia;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\TarifaCoste;
use App\Services\ShiftAssignmentValidator;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;
use App\Services\EmployeeHoursSummaryService;

class EmployeeShow extends Component
{
    public Empleado $empleado;
    public string $mesResumen = '';
    public string $activeTab = 'contratos'; // contratos, ausencias, incidencias, asignaciones, tarifas

    // Formulario de Contrato
    public bool $modalContratoOpen = false;
    public string $tipo_contrato = 'Indefinido';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public float $jornada_semanal_horas = 40.0;
    public string $observaciones_contrato = '';

    // Formulario de Ausencia
    public bool $modalAusenciaOpen = false;
    public string $tipo_ausencia = 'vacaciones';
    public string $fecha_hora_inicio = '';
    public string $fecha_hora_fin = '';
    public string $observaciones_ausencia = '';

    // Formulario de Tarifa
    public bool $modalTarifaOpen = false;
    public string $modoTarifa = 'normal';
    public ?float $coste_hora = null;
    public string $tarifa_fecha_inicio = '';
    public string $tarifa_fecha_fin = '';
    public string $observaciones_tarifa = '';

    public function mount(int $id): void
    {
        $this->empleado = Empleado::with(['categoria', 'contratos', 'ausencias', 'incidencias', 'tarifasCoste', 'user'])->findOrFail($id);
        $this->fecha_inicio = now()->toDateString();
        $this->tarifa_fecha_inicio = now()->toDateString();
        $this->mesResumen = now()->format('Y-m');
    }

    public function tarifaBaseCategoriaEnFecha(): ?float
    {
        if (! $this->empleado->categoria_id || ! $this->tarifa_fecha_inicio) {
            return null;
        }

        $tarifa = TarifaCoste::query()
            ->where('categoria_id', $this->empleado->categoria_id)
            ->whereNull('empleado_id')
            ->whereDate('fecha_inicio', '<=', $this->tarifa_fecha_inicio)
            ->where(function ($query) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $this->tarifa_fecha_inicio);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        return $tarifa ? (float) $tarifa->coste_hora : null;
    }

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function guardarContrato(): void
    {
        // Solo RR. HH. y administradores pueden modificar contratos.
        Gate::authorize('update', $this->empleado);

        $this->validate([
            'tipo_contrato' => 'required|string|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'jornada_semanal_horas' => 'required|numeric|min:1|max:60',
            'observaciones_contrato' => 'nullable|string|max:500',
        ], [
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio del contrato.',
        ]);

        Contrato::create([
            'empleado_id' => $this->empleado->id,
            'tipo_contrato' => $this->tipo_contrato,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin ?: null,
            'jornada_semanal_horas' => $this->jornada_semanal_horas,
            'observaciones' => $this->observaciones_contrato ?: null,
        ]);

        $this->modalContratoOpen = false;
        $this->empleado->load('contratos');
        $this->feedbackMessage = 'Contrato laboral registrado correctamente en el historial.';
        $this->feedbackError = null;
    }

    public function eliminarContrato(int $id): void
    {
        // Comprobar permisos ANTES de intentar eliminar.
        Gate::authorize('update', $this->empleado);

        try {
            $contrato = Contrato::where('empleado_id', $this->empleado->id)
                ->findOrFail($id);

            $contrato->delete();
            $contrato = Contrato::where('empleado_id', $this->empleado->id)->findOrFail($id);
            $contrato->delete();
            $this->empleado->load('contratos');
            $this->feedbackMessage = 'Contrato eliminado correctamente del historial.';
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar el contrato: ' . $e->getMessage();
            $this->feedbackMessage = null;
        }
    }

    public function guardarAusencia(): void
    {
        // Comprobar autorización antes de registrar o aprobar ausencias.
        Gate::authorize('update', $this->empleado);

        $this->validate([
            'tipo_ausencia' => 'required|in:vacaciones,baja,permiso,otro',
            'fecha_hora_inicio' => 'required|date',
            'fecha_hora_fin' => 'required|date|after_or_equal:fecha_hora_inicio',
            'observaciones_ausencia' => 'nullable|string|max:500',
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $this->empleado->id,
            'tipo' => $this->tipo_ausencia,
            'fecha_hora_inicio' => $this->fecha_hora_inicio,
            'fecha_hora_fin' => $this->fecha_hora_fin,
            'estado' => Ausencia::ESTADO_APROBADA, // Registrada desde RRHH aprobada directamente
            'observaciones' => $this->observaciones_ausencia ?: null,
            'aprobado_por' => auth()->id(),
            'aprobado_en' => now(),
        ]);

        // Marcar conflictos si afecta a turnos existentes
        $validator = new ShiftAssignmentValidator();
        $conflictos = $validator->marcarConflictosPorAusencia($ausencia);

        $this->modalAusenciaOpen = false;
        $this->empleado->load('ausencias');

        if ($conflictos > 0) {
            $this->feedbackMessage = "Ausencia aprobada. ¡Se han detectado {$conflictos} turnos asignados en conflicto que requieren revisión!";
        } else {
            $this->feedbackMessage = 'Ausencia aprobada y registrada.';
        }
        $this->feedbackError = null;
    }
    public function eliminarAusencia(int $id): void
    {
        // Comprobar permisos antes de eliminar la ausencia.
        Gate::authorize('update', $this->empleado);

        try {
            $ausencia = Ausencia::where('empleado_id', $this->empleado->id)->findOrFail($id);
            $inicio = $ausencia->fecha_hora_inicio;
            $fin = $ausencia->fecha_hora_fin;
            $ausencia->delete();

            $asignacionesEnConflicto = \App\Models\Asignacion::where('empleado_id', $this->empleado->id)
                ->where('tiene_conflicto', true)
                ->where('motivo_conflicto', 'like', '%Ausencia aprobada sobrevenida%')
                ->whereHas('turno', function ($q) use ($inicio, $fin) {
                    $q->where('hora_inicio', '<', $fin)
                        ->where('hora_fin', '>', $inicio);
                })
                ->get();

            foreach ($asignacionesEnConflicto as $asig) {
                $otra = Ausencia::where('empleado_id', $this->empleado->id)
                    ->where('estado', Ausencia::ESTADO_APROBADA)
                    ->where('fecha_hora_inicio', '<', $asig->turno->hora_fin)
                    ->where('fecha_hora_fin', '>', $asig->turno->hora_inicio)
                    ->exists();

                if (! $otra) {
                    $asig->update([
                        'tiene_conflicto' => false,
                        'motivo_conflicto' => null,
                    ]);
                }
            }

            $this->empleado->load('ausencias');
            $this->feedbackMessage = 'Registro de ausencia eliminado con éxito.';
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar la ausencia: ' . $e->getMessage();
            $this->feedbackMessage = null;
        }
    }
    public function abrirModalTarifa(): void
    {
        Gate::authorize('update', $this->empleado);

        // Limpiar los datos introducidos anteriormente.
        $this->coste_hora = null;
        $this->tarifa_fecha_inicio = now()->toDateString();
        $this->tarifa_fecha_fin = '';
        $this->observaciones_tarifa = '';
        $this->modoTarifa = 'normal';

        // Limpiar errores de intentos anteriores.
        $this->resetValidation();

        // Abrir el formulario limpio.
        $this->modalTarifaOpen = true;
    }
    public function guardarTarifa(): void
    {
        // Solo RR. HH. y administradores pueden modificar tarifas.
        Gate::authorize('update', $this->empleado);

        $this->validate([
            'coste_hora' => 'required|numeric|min:0',
            'tarifa_fecha_inicio' => 'required|date',
            'tarifa_fecha_fin' => $this->modoTarifa === 'excepcion'
                ? 'required|date|after_or_equal:tarifa_fecha_inicio'
                : 'nullable|date|after_or_equal:tarifa_fecha_inicio',
            'observaciones_tarifa' => 'nullable|string|max:500',
            'modoTarifa' => 'required|in:normal,excepcion',
        ]);

        // Modalidad: excepción temporal sobre tarifa individual existente.
        if ($this->modoTarifa === 'excepcion') {

            app(\App\Services\CostRatePeriodService::class)
                ->crearExcepcionTemporalEmpleado(
                    $this->empleado->id,
                    (float) $this->coste_hora,
                    $this->tarifa_fecha_inicio,
                    $this->tarifa_fecha_fin,
                    $this->observaciones_tarifa
                );

            $this->modalTarifaOpen = false;

            $this->empleado->load('tarifasCoste');

            $this->feedbackMessage =
                'Excepción temporal registrada. '
                . 'La tarifa anterior se recuperará automáticamente '
                . 'al finalizar el período.';

            $this->feedbackError = null;

            return;
        }
        // Comprobar que la nueva tarifa no se solapa con otra existente.
        $existeConflicto = app(
            \App\Services\CostRatePeriodService::class
        )->existeSolapamiento(
            $this->empleado->id,
            null,
            $this->tarifa_fecha_inicio,
            $this->tarifa_fecha_fin ?: null
        );

        if ($existeConflicto) {
            $this->addError(
                'tarifa_fecha_inicio',
                'Ya existe una tarifa para este empleado '
                    . 'con un período de vigencia que se solapa.'
            );

            return;
        }

        TarifaCoste::create([
            'empleado_id' => $this->empleado->id,
            'coste_hora' => $this->coste_hora,
            'fecha_inicio' => $this->tarifa_fecha_inicio,
            'fecha_fin' => $this->tarifa_fecha_fin ?: null,
            'observaciones' => $this->observaciones_tarifa ?: null,
        ]);

        $this->modalTarifaOpen = false;
        $this->empleado->load('tarifasCoste');
        $this->feedbackMessage = 'Tarifa personalizada asignada al empleado.';
        $this->feedbackError = null;
    }

    public function eliminarTarifa(int $id): void
    {
        // Solo RR. HH. y administradores pueden modificar tarifas.
        Gate::authorize('update', $this->empleado);

        try {
            $tarifa = TarifaCoste::where('empleado_id', $this->empleado->id)->findOrFail($id);
            $tarifa->delete();
            $this->empleado->load('tarifasCoste');
            $this->feedbackMessage = 'Tarifa personalizada eliminada con éxito.';
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar la tarifa: ' . $e->getMessage();
            $this->feedbackMessage = null;
        }
    }

    public function render()
    {
        // Historial paginado de asignaciones.
        $asignaciones = $this->empleado->asignaciones()
            ->with(['turno.instalacion.cliente', 'turno.actividad', 'registroHora'])
            ->latest()
            ->paginate(10);

        // Validar el formato del mes seleccionado.
        $mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mesResumen)
            ? $this->mesResumen
            : now()->format('Y-m');

        // Determinar el inicio y el final del mes.
        $inicio = Carbon::createFromFormat('!Y-m', $mes)
            ->startOfMonth();

        $fin = $inicio->copy()->endOfMonth();

        // Calcular las horas de este empleado.
        $resumenHoras = app(EmployeeHoursSummaryService::class)->calcular(
            $this->empleado,
            $inicio->toDateString(),
            $fin->toDateString()
        );

        return view('livewire.rrhh.employee-show', [
            'asignaciones' => $asignaciones,
            'resumenHoras' => $resumenHoras,
        ])->layout('layouts.app', [
            'title' => 'Ficha: ' . $this->empleado->nombre_completo,
        ]);
    }
}
