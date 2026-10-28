<?php

namespace App\Livewire\Rrhh;

use App\Models\Ausencia;
use App\Models\Empleado;
use App\Services\ShiftAssignmentValidator;
use Livewire\Component;
use Livewire\WithPagination;

class AbsenceIndex extends Component
{
    use WithPagination;

    public string $estadoFiltro = '';
    public string $tipoFiltro = '';
    public ?int $empleadoFiltro = null;

    public bool $modalCrearOpen = false;
    public ?int $empleado_id = null;
    public string $tipo = 'vacaciones';
    public string $fecha_hora_inicio = '';
    public string $fecha_hora_fin = '';
    public string $observaciones = '';

    public function abrirModalCrear(): void
    {
        $this->reset(['empleado_id', 'observaciones']);
        $this->tipo = 'vacaciones';
        $this->fecha_hora_inicio = now()->format('Y-m-d\T09:00');
        $this->fecha_hora_fin = now()->addDays(2)->format('Y-m-d\T18:00');
        $this->empleado_id = Empleado::where('activo', true)->first()?->id;
        $this->modalCrearOpen = true;
    }

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function registrarAusencia(): void
    {
        $this->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'tipo' => 'required|in:vacaciones,baja,permiso,otro',
            'fecha_hora_inicio' => 'required|date',
            'fecha_hora_fin' => 'required|date|after_or_equal:fecha_hora_inicio',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $this->empleado_id,
            'tipo' => $this->tipo,
            'fecha_hora_inicio' => $this->fecha_hora_inicio,
            'fecha_hora_fin' => $this->fecha_hora_fin,
            'estado' => Ausencia::ESTADO_APROBADA,
            'observaciones' => $this->observaciones ?: null,
            'aprobado_por' => auth()->id(),
            'aprobado_en' => now(),
        ]);

        $validator = new ShiftAssignmentValidator();
        $conflictos = $validator->marcarConflictosPorAusencia($ausencia);

        $this->modalCrearOpen = false;

        if ($conflictos > 0) {
            $this->feedbackMessage = "Ausencia registrada. Se han detectado {$conflictos} turnos existentes en conflicto que requieren revisión en el cuadrante.";
        } else {
            $this->feedbackMessage = 'Ausencia registrada y aprobada correctamente.';
        }
        $this->feedbackError = null;
    }

    public function aprobar(int $id): void
    {
        $ausencia = Ausencia::findOrFail($id);
        $ausencia->update([
            'estado' => Ausencia::ESTADO_APROBADA,
            'aprobado_por' => auth()->id(),
            'aprobado_en' => now(),
        ]);

        $validator = new ShiftAssignmentValidator();
        $conflictos = $validator->marcarConflictosPorAusencia($ausencia);

        if ($conflictos > 0) {
            $this->feedbackMessage = "Ausencia aprobada. ¡Atención: Hay {$conflictos} turnos asignados del empleado en conflicto con esta ausencia!";
        } else {
            $this->feedbackMessage = 'Ausencia aprobada.';
        }
        $this->feedbackError = null;
    }

    public function rechazar(int $id): void
    {
        $ausencia = Ausencia::findOrFail($id);
        $ausencia->update([
            'estado' => Ausencia::ESTADO_RECHAZADA,
            'aprobado_por' => auth()->id(),
            'aprobado_en' => now(),
        ]);

        $this->feedbackMessage = 'Solicitud de ausencia rechazada.';
        $this->feedbackError = null;
    }

    public function eliminar(int $id): void
    {
        try {
            $ausencia = Ausencia::findOrFail($id);
            $empleadoId = $ausencia->empleado_id;
            $inicio = $ausencia->fecha_hora_inicio;
            $fin = $ausencia->fecha_hora_fin;

            $ausencia->delete();

            // Limpiar posibles conflictos derivados de esta ausencia en turnos asignados
            $asignacionesEnConflicto = \App\Models\Asignacion::where('empleado_id', $empleadoId)
                ->where('tiene_conflicto', true)
                ->where('motivo_conflicto', 'like', '%Ausencia aprobada sobrevenida%')
                ->whereHas('turno', function ($q) use ($inicio, $fin) {
                    $q->where('hora_inicio', '<', $fin)
                      ->where('hora_fin', '>', $inicio);
                })
                ->get();

            foreach ($asignacionesEnConflicto as $asig) {
                $otraAusencia = Ausencia::where('empleado_id', $empleadoId)
                    ->where('estado', Ausencia::ESTADO_APROBADA)
                    ->where('fecha_hora_inicio', '<', $asig->turno->hora_fin)
                    ->where('fecha_hora_fin', '>', $asig->turno->hora_inicio)
                    ->exists();

                if (! $otraAusencia) {
                    $asig->update([
                        'tiene_conflicto' => false,
                        'motivo_conflicto' => null,
                    ]);
                }
            }

            $this->feedbackMessage = 'Registro de ausencia eliminado con éxito.';
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar la ausencia: ' . $e->getMessage();
            $this->feedbackMessage = null;
        }
    }

    public function render()
    {
        $query = Ausencia::with(['empleado.categoria', 'aprobador'])
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('estado', $this->estadoFiltro))
            ->when($this->tipoFiltro !== '', fn ($q) => $q->where('tipo', $this->tipoFiltro))
            ->when($this->empleadoFiltro, fn ($q) => $q->where('empleado_id', $this->empleadoFiltro))
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 1 ELSE 2 END")
            ->orderBy('fecha_hora_inicio', 'desc');

        return view('livewire.rrhh.absence-index', [
            'ausencias' => $query->paginate(10),
            'empleados' => Empleado::where('activo', true)->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Gestión de Ausencias y Bajas']);
    }
}
