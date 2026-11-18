<?php

namespace App\Livewire\Roster;

use App\Models\Proyecto;
use App\Models\Turno;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

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


    public bool $modalPublicacionOpen = false;
    #[Locked]
    public ?string $fechaDesdePublicacion = null;
    #[Locked]
    public ?string $fechaHastaPublicacion = null;
    #[Locked]
    public ?int $proyectoFiltroPublicacion = null;

    public array $proyectosSeleccionados = [];

    public function abrirModalPublicacion(): void
    {
        Gate::authorize('publish', Turno::class);

        $this->proyectosSeleccionados = [];
        $this->modalPublicacionOpen = true;
        $this->fechaDesdePublicacion = $this->fechaDesde;
        $this->fechaHastaPublicacion = $this->fechaHasta;
        $this->proyectoFiltroPublicacion = $this->proyectoFiltro;
    }

    public function publicarBorradores(): void
    {
        Gate::authorize('publish', Turno::class);

        if ($this->proyectoFiltro !== $this->proyectoFiltroPublicacion) {
            $this->feedbackMessage = null;
            $this->feedbackError = 'El proyecto filtrado ha cambiado. Cerrá y volvé a abrir el modal de publicación.';
            return;
        }

        $this->feedbackMessage = null;
        $this->feedbackError = null;


        if (! $this->modalPublicacionOpen || empty($this->proyectosSeleccionados)) {
            $this->feedbackError = 'Debes seleccionar al menos un proyecto.';
            return;
        }

        if (
            $this->fechaDesde !== $this->fechaDesdePublicacion ||
            $this->fechaHasta !== $this->fechaHastaPublicacion
        ) {
            $this->feedbackMessage = null;
            $this->feedbackError = 'El período ha cambiado. Cerrá y volvé a abrir el modal de publicación.';
            return;
        }

        $proyectosSeleccionados = array_values(array_unique(
            array_map('intval', $this->proyectosSeleccionados)
        ));

        // Comprobar que todos los proyectos seleccionados tengan
        // turnos en borrador dentro del período y filtros actuales.
        $proyectosPermitidos = Turno::query()
            ->where('estado', Turno::ESTADO_BORRADOR)
            ->whereBetween('fecha', [$this->fechaDesde, $this->fechaHasta])
            ->when(
                $this->proyectoFiltro,
                fn($q) => $q->where('proyecto_id', $this->proyectoFiltro)
            )
            ->whereIn('proyecto_id', $proyectosSeleccionados)
            ->distinct()
            ->pluck('proyecto_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (count($proyectosPermitidos) !== count($proyectosSeleccionados)) {
            $this->feedbackError = 'La selección contiene proyectos sin turnos pendientes en el período.';
            return;
        }

        $publicados = Turno::query()
            ->where('estado', Turno::ESTADO_BORRADOR)
            ->whereBetween('fecha', [$this->fechaDesde, $this->fechaHasta])
            ->whereIn('proyecto_id', $proyectosSeleccionados)
            ->when(
                $this->proyectoFiltro,
                fn($q) => $q->where('proyecto_id', $this->proyectoFiltro)
            )
            ->update(['estado' => Turno::ESTADO_PUBLICADO]);

        $this->modalPublicacionOpen = false;
        $this->proyectosSeleccionados = [];

        $this->feedbackMessage = "Se han publicado {$publicados} turnos en el cuadrante.";
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
            'proyectosPendientesPublicacion' => Proyecto::query()
                ->whereHas('turnos', function ($query) {
                    $query->where('estado', Turno::ESTADO_BORRADOR)
                        ->whereBetween('fecha', [
                            $this->fechaDesde,
                            $this->fechaHasta,
                        ])
                        ->when(
                            $this->proyectoFiltro,
                            fn($q) => $q->where('proyecto_id', $this->proyectoFiltro)
                        );
                })
                ->withCount([
                    'turnos as turnos_pendientes_count' => function ($query) {
                        $query->where('estado', Turno::ESTADO_BORRADOR)
                            ->whereBetween('fecha', [
                                $this->fechaDesde,
                                $this->fechaHasta,
                            ]);
                    },
                ])
                ->orderBy('nombre')
                ->get(),
        ])->layout('layouts.app', ['title' => 'Gestión de Turnos']);
    }
}
