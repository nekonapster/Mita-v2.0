<?php

namespace App\Livewire\Services;

use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Instalacion;
use App\Models\NecesidadServicio;
use App\Models\Proyecto;
use App\Models\Turno;
use App\Services\ShiftGenerationService;
use App\Services\ShiftSyncPreviewService;
use Livewire\Component;
use Livewire\WithPagination;

class ServiceNeedsManager extends Component
{
    use WithPagination;

    public ?int $proyectoFiltro = null;
    public ?int $instalacionFiltro = null;

    // Modal Crear/Editar Necesidad
    public bool $modalOpen = false;
    public ?int $necesidadId = null;
    public ?int $proyecto_id = null;
    public ?int $instalacion_id = null;
    public ?int $actividad_id = null;
    public ?int $categoria_id = null;
    public string $fecha_inicio_vigencia = '';
    public string $fecha_fin_vigencia = '';
    public array $dias_semana = [1, 2, 3, 4, 5];
    public string $hora_inicio = '09:00';
    public string $hora_fin = '17:00';
    public bool $cruza_medianoche = false;
    public int $plazas_necesarias = 1;
    public string $observaciones = '';

    // Modal Generar Turnos
    public bool $modalGenerarOpen = false;
    public ?int $necesidadParaGenerarId = null;
    public string $genFechaDesde = '';
    public string $genFechaHasta = '';
    public string $genEstado = Turno::ESTADO_PUBLICADO;

    // Modal Sincronizar / Previsualizar Cambios
    public bool $modalSyncOpen = false;
    public ?int $necesidadParaSyncId = null;
    public ?array $syncPreviewData = null;

    public function mount(?int $proyectoFiltro = null): void
    {
        if ($proyectoFiltro) {
            $this->proyectoFiltro = $proyectoFiltro;
        }
        $this->fecha_inicio_vigencia = now()->toDateString();
        $this->fecha_fin_vigencia = now()->endOfYear()->toDateString();
        $this->genFechaDesde = now()->toDateString();
        $this->genFechaHasta = now()->addWeeks(2)->toDateString();
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['necesidadId', 'observaciones']);
        $this->proyecto_id = $this->proyectoFiltro ?: Proyecto::first()?->id;
        $this->actualizarInstalacionesDisponibles();
        $this->actividad_id = Actividad::first()?->id;
        $this->categoria_id = Categoria::first()?->id;
        $this->dias_semana = [1, 2, 3, 4, 5];
        $this->hora_inicio = '10:00';
        $this->hora_fin = '18:00';
        $this->cruza_medianoche = false;
        $this->plazas_necesarias = 1;
        $this->fecha_inicio_vigencia = now()->toDateString();
        $this->fecha_fin_vigencia = now()->endOfYear()->toDateString();
        $this->modalOpen = true;
    }

    public function updatedProyectoId(): void
    {
        $this->actualizarInstalacionesDisponibles();
    }

    private function actualizarInstalacionesDisponibles(): void
    {
        if ($this->proyecto_id) {
            $proy = Proyecto::with('instalaciones')->find($this->proyecto_id);
            $this->instalacion_id = $proy?->instalaciones->first()?->id;
        }
    }

    public function abrirModalEditar(int $id): void
    {
        $nec = NecesidadServicio::findOrFail($id);
        $this->necesidadId = $nec->id;
        $this->proyecto_id = $nec->proyecto_id;
        $this->instalacion_id = $nec->instalacion_id;
        $this->actividad_id = $nec->actividad_id;
        $this->categoria_id = $nec->categoria_id;
        $this->fecha_inicio_vigencia = $nec->fecha_inicio_vigencia->toDateString();
        $this->fecha_fin_vigencia = $nec->fecha_fin_vigencia->toDateString();
        $this->dias_semana = (array) $nec->dias_semana;
        $this->hora_inicio = substr($nec->hora_inicio, 0, 5);
        $this->hora_fin = substr($nec->hora_fin, 0, 5);
        $this->cruza_medianoche = (bool) $nec->cruza_medianoche;
        $this->plazas_necesarias = $nec->plazas_necesarias;
        $this->observaciones = $nec->observaciones ?? '';
        $this->modalOpen = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'proyecto_id' => 'required|exists:proyectos,id',
            'instalacion_id' => 'required|exists:instalaciones,id',
            'actividad_id' => 'required|exists:actividades,id',
            'categoria_id' => 'required|exists:categorias,id',
            'fecha_inicio_vigencia' => 'required|date',
            'fecha_fin_vigencia' => 'required|date|after_or_equal:fecha_inicio_vigencia',
            'dias_semana' => 'required|array|min:1',
            'hora_inicio' => 'required',
            'hora_fin' => 'required',
            'plazas_necesarias' => 'required|integer|min:1|max:20',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $nec = NecesidadServicio::updateOrCreate(
            ['id' => $this->necesidadId],
            [
                'proyecto_id' => $this->proyecto_id,
                'instalacion_id' => $this->instalacion_id,
                'actividad_id' => $this->actividad_id,
                'categoria_id' => $this->categoria_id,
                'fecha_inicio_vigencia' => $this->fecha_inicio_vigencia,
                'fecha_fin_vigencia' => $this->fecha_fin_vigencia,
                'dias_semana' => array_map('intval', $this->dias_semana),
                'hora_inicio' => $this->hora_inicio,
                'hora_fin' => $this->hora_fin,
                'cruza_medianoche' => $this->cruza_medianoche,
                'plazas_necesarias' => $this->plazas_necesarias,
                'observaciones' => $this->observaciones ?: null,
            ]
        );

        $this->modalOpen = false;
        session()->flash('success', $this->necesidadId ? 'Necesidad de servicio actualizada.' : 'Necesidad de servicio creada.');
    }

    public function abrirModalGenerar(int $id): void
    {
        $this->necesidadParaGenerarId = $id;
        $nec = NecesidadServicio::findOrFail($id);
        $this->genFechaDesde = now()->toDateString();
        $this->genFechaHasta = now()->addWeeks(2)->toDateString();
        $this->modalGenerarOpen = true;
    }

    public function generarTurnos(): void
    {
        $this->validate([
            'genFechaDesde' => 'required|date',
            'genFechaHasta' => 'required|date|after_or_equal:genFechaDesde',
            'genEstado' => 'required|in:borrador,publicado',
        ]);

        $nec = NecesidadServicio::findOrFail($this->necesidadParaGenerarId);
        $service = new ShiftGenerationService();
        $res = $service->generarTurnos($nec, $this->genFechaDesde, $this->genFechaHasta, $this->genEstado);

        $this->modalGenerarOpen = false;
        session()->flash('success', "Se han generado {$res['generados']} turnos con éxito (y se omitieron {$res['omitidos']} turnos ya existentes).");
    }

    public function abrirModalSync(int $id): void
    {
        $this->necesidadParaSyncId = $id;
        $nec = NecesidadServicio::findOrFail($id);
        $previewService = new ShiftSyncPreviewService();
        $this->syncPreviewData = $previewService->previsualizarImpacto($nec);
        $this->modalSyncOpen = true;
    }

    public function confirmarSync(bool $incluirPublicados = false): void
    {
        $nec = NecesidadServicio::findOrFail($this->necesidadParaSyncId);
        $syncService = new ShiftSyncPreviewService();
        $res = $syncService->sincronizarTurnos($nec, $incluirPublicados);

        $this->modalSyncOpen = false;
        session()->flash('success', "Sincronización completada. Se han actualizado {$res['actualizados']} turnos.");
    }

    public bool $modalDeleteOpen = false;
    public ?int $necesidadToDeleteId = null;
    public int $turnosAsociadosCount = 0;
    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function confirmarEliminar(int $id): void
    {
        $nec = NecesidadServicio::withCount('turnos')->findOrFail($id);
        $this->necesidadToDeleteId = $nec->id;
        $this->turnosAsociadosCount = $nec->turnos_count;
        $this->feedbackMessage = null;
        $this->feedbackError = null;
        $this->modalDeleteOpen = true;
    }

    public function eliminar(): void
    {
        if (! $this->necesidadToDeleteId) {
            return;
        }

        try {
            $nec = NecesidadServicio::with('turnos.asignacionesActivas')->findOrFail($this->necesidadToDeleteId);

            foreach ($nec->turnos as $turno) {
                if ($turno->asignacionesActivas()->count() === 0) {
                    $turno->delete();
                } else {
                    $turno->update(['estado' => Turno::ESTADO_CANCELADO]);
                }
            }

            $nec->delete();
            $this->modalDeleteOpen = false;
            $this->reset(['necesidadToDeleteId', 'turnosAsociadosCount']);
            $this->feedbackMessage = 'Necesidad de servicio eliminada correctamente.';
            session()->flash('success', $this->feedbackMessage);
        } catch (\Throwable $e) {
            $this->modalDeleteOpen = false;
            $this->feedbackError = 'No se pudo eliminar la necesidad de servicio: ' . $e->getMessage();
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = NecesidadServicio::with(['proyecto', 'instalacion', 'actividad', 'categoria', 'turnos'])
            ->when($this->proyectoFiltro, fn ($q) => $q->where('proyecto_id', $this->proyectoFiltro))
            ->when($this->instalacionFiltro, fn ($q) => $q->where('instalacion_id', $this->instalacionFiltro))
            ->orderBy('id', 'desc');

        $proyectos = Proyecto::with('instalaciones')->get();
        $instalacionesDelProyecto = $this->proyecto_id
            ? Proyecto::find($this->proyecto_id)?->instalaciones ?? collect()
            : collect();

        return view('livewire.services.service-needs-manager', [
            'necesidades' => $query->paginate(10),
            'proyectos' => $proyectos,
            'instalacionesDelProyecto' => $instalacionesDelProyecto,
            'actividades' => Actividad::where('activo', true)->get(),
            'categorias' => Categoria::where('activo', true)->get(),
        ])->layout('layouts.app', ['title' => 'Necesidades de Servicio']);
    }
}
