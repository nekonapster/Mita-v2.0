<?php

namespace App\Livewire\Roster;

use App\Models\Proyecto;
use App\Models\Turno;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;

class ShiftManager extends Component
{
    use WithPagination;

    public string $estadoFiltro = '';
    public ?int $proyectoFiltro = null;
    public string $fechaDesde = '';
    public string $fechaHasta = '';

    public function mount(): void
    {
        $this->fechaDesde = now()->startOfWeek()->toDateString();
        $this->fechaHasta = now()->addWeeks(2)->endOfWeek()->toDateString();
    }

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;
    public bool $modalDeleteOpen = false;
    public ?int $turnoToDeleteId = null;
    public ?string $turnoToDeleteInfo = null;
    public bool $turnoTieneAsignaciones = false;

    public function publicarBorradores(): void
    {
        Gate::authorize('publish', Turno::class);

        $turnos = Turno::where('estado', Turno::ESTADO_BORRADOR)
            ->whereBetween('fecha', [$this->fechaDesde, $this->fechaHasta])
            ->when($this->proyectoFiltro, fn($q) => $q->where('proyecto_id', $this->proyectoFiltro))
            ->update(['estado' => Turno::ESTADO_PUBLICADO]);

        $this->feedbackMessage = "Se han publicado {$turnos} turnos en el cuadrante.";
        $this->feedbackError = null;
    }


    public function cambiarEstado(int $id, string $nuevoEstado): void
    {
        $turno = Turno::findOrFail($id);

        // 1. Comprobar permisos
        \Illuminate\Support\Facades\Gate::authorize('update', $turno);

        // 2. Validar estados permitidos
        $estadosPermitidos = [
            Turno::ESTADO_BORRADOR,
            Turno::ESTADO_PUBLICADO,
            Turno::ESTADO_CANCELADO,
        ];

        if (!in_array($nuevoEstado, $estadosPermitidos, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'nuevoEstado' => 'El estado indicado no es válido.',
            ]);
        }

        // 3. Impedir reactivar turnos cancelados automáticamente
        if (
            $turno->estado === Turno::ESTADO_CANCELADO &&
            $nuevoEstado !== Turno::ESTADO_CANCELADO
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'nuevoEstado' => 'Un turno cancelado requiere revisión para reactivarse.',
            ]);
        }

        // 4. Cancelación del turno y sus asignaciones
        if ($nuevoEstado === Turno::ESTADO_CANCELADO) {

            \Illuminate\Support\Facades\DB::transaction(function () use ($id) {

                $turno = Turno::lockForUpdate()->findOrFail($id);

                // Proteger cualquier registro de horas existente
                if ($turno->asignaciones()->whereHas('registroHora')->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nuevoEstado' => 'El turno tiene registros de horas y requiere revisión antes de cancelarse.',
                    ]);
                }

                // Cancelar todas las asignaciones sin eliminarlas
                $turno->asignaciones()->update([
                    'estado' => \App\Models\Asignacion::ESTADO_CANCELADO,
                ]);

                // Cancelar el turno
                $turno->update([
                    'estado' => Turno::ESTADO_CANCELADO,
                ]);
            });

            $this->feedbackMessage = 'Turno y asignaciones cancelados correctamente.';
            $this->feedbackError = null;

            return;
        }

        // 5. Resto de cambios de estado
        $turno->update([
            'estado' => $nuevoEstado,
        ]);

        $this->feedbackMessage = "Turno actualizado a estado '{$nuevoEstado}'.";
        $this->feedbackError = null;
    }

    public function confirmarEliminar(int $id): void
    {
        $turno = Turno::with(['instalacion', 'actividad', 'asignaciones.registroHora'])->findOrFail($id);

        $horasValidadas = $turno->asignaciones->filter(fn($a) => $a->registroHora?->estado === 'validado')->count();
        if ($horasValidadas > 0) {
            $this->feedbackError = "No se puede eliminar este turno porque tiene {$horasValidadas} registro(s) de horas ya validados. Puedes cancelarlo en su lugar para mantener la integridad histórica.";
            $this->feedbackMessage = null;
            return;
        }

        $this->turnoToDeleteId = $id;
        $this->turnoToDeleteInfo = "{$turno->fecha->format('d/m/Y')} ({$turno->instalacion->nombre} · {$turno->actividad->nombre})";
        $this->turnoTieneAsignaciones = $turno->asignaciones->count() > 0;
        $this->modalDeleteOpen = true;
    }

    public function ejecutarEliminar(): void
    {
        if (! $this->turnoToDeleteId) {
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
                $turno = Turno::with('asignaciones.registroHora')->findOrFail($this->turnoToDeleteId);

                foreach ($turno->asignaciones as $asig) {
                    if ($asig->registroHora) {
                        if ($asig->registroHora->estado === 'validado') {
                            throw new \Exception('No se puede eliminar una asignación con horas validadas.');
                        }
                        $asig->registroHora->delete();
                    }
                    $asig->delete();
                }

                $turno->delete();
            });

            $this->feedbackMessage = "Turno '{$this->turnoToDeleteInfo}' eliminado correctamente.";
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar el turno: ' . $e->getMessage();
            $this->feedbackMessage = null;
        } finally {
            $this->modalDeleteOpen = false;
            $this->turnoToDeleteId = null;
        }
    }

    public function render()
    {
        $query = Turno::with(['proyecto', 'instalacion', 'actividad', 'categoria', 'asignacionesActivas.empleado'])
            ->when($this->estadoFiltro !== '', fn($q) => $q->where('estado', $this->estadoFiltro))
            ->when($this->proyectoFiltro, fn($q) => $q->where('proyecto_id', $this->proyectoFiltro))
            ->when($this->fechaDesde, fn($q) => $q->where('fecha', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->where('fecha', '<=', $this->fechaHasta))
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        return view('livewire.roster.shift-manager', [
            'turnos' => $query->paginate(15),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Gestión de Turnos']);
    }
}
