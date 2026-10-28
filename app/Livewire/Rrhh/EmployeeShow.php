<?php

namespace App\Livewire\Rrhh;

use App\Models\Ausencia;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\TarifaCoste;
use App\Services\ShiftAssignmentValidator;
use Carbon\Carbon;
use Livewire\Component;

class EmployeeShow extends Component
{
    public Empleado $empleado;
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
    public float $coste_hora = 15.0;
    public string $tarifa_fecha_inicio = '';
    public string $tarifa_fecha_fin = '';
    public string $observaciones_tarifa = '';

    public function mount(int $id): void
    {
        $this->empleado = Empleado::with(['categoria', 'contratos', 'ausencias', 'incidencias', 'tarifasCoste', 'user'])->findOrFail($id);
        $this->fecha_inicio = now()->toDateString();
        $this->tarifa_fecha_inicio = now()->toDateString();
    }

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function guardarContrato(): void
    {
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
        try {
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

    public function guardarTarifa(): void
    {
        $this->validate([
            'coste_hora' => 'required|numeric|min:0',
            'tarifa_fecha_inicio' => 'required|date',
            'tarifa_fecha_fin' => 'nullable|date|after_or_equal:tarifa_fecha_inicio',
            'observaciones_tarifa' => 'nullable|string|max:500',
        ]);

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
        $asignaciones = $this->empleado->asignaciones()
            ->with(['turno.instalacion.cliente', 'turno.actividad', 'registroHora'])
            ->latest()
            ->paginate(10);

        return view('livewire.rrhh.employee-show', [
            'asignaciones' => $asignaciones,
        ])->layout('layouts.app', ['title' => 'Ficha: ' . $this->empleado->nombre_completo]);
    }
}
