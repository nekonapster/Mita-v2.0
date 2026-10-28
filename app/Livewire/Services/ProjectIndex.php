<?php

namespace App\Livewire\Services;

use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\Proyecto;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $estadoFiltro = '';
    public ?int $clienteFiltro = null;

    public bool $modalOpen = false;
    public ?int $proyectoId = null;
    public ?int $cliente_id = null;
    public string $nombre = '';
    public string $descripcion = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $estado = 'borrador';
    public array $instalacionesSeleccionadas = [];
    public string $observaciones = '';

    public function mount(): void
    {
        $this->fecha_inicio = now()->toDateString();
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['proyectoId', 'nombre', 'descripcion', 'observaciones', 'instalacionesSeleccionadas']);
        $this->cliente_id = Cliente::first()?->id;
        $this->fecha_inicio = now()->toDateString();
        $this->fecha_fin = '';
        $this->estado = 'borrador';
        $this->modalOpen = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $proyecto = Proyecto::with('instalaciones')->findOrFail($id);
        $this->proyectoId = $proyecto->id;
        $this->cliente_id = $proyecto->cliente_id;
        $this->nombre = $proyecto->nombre;
        $this->descripcion = $proyecto->descripcion ?? '';
        $this->fecha_inicio = $proyecto->fecha_inicio->toDateString();
        $this->fecha_fin = $proyecto->fecha_fin ? $proyecto->fecha_fin->toDateString() : '';
        $this->estado = $proyecto->estado;
        $this->observaciones = $proyecto->observaciones ?? '';
        $this->instalacionesSeleccionadas = $proyecto->instalaciones->pluck('id')->toArray();
        $this->modalOpen = true;
    }

    public function guardarProyecto(): void
    {
        $this->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:1000',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'estado' => 'required|in:borrador,activo,finalizado,cancelado',
            'instalacionesSeleccionadas' => 'nullable|array',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $proyecto = Proyecto::updateOrCreate(
            ['id' => $this->proyectoId],
            [
                'cliente_id' => $this->cliente_id,
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion ?: null,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin ?: null,
                'estado' => $this->estado,
                'observaciones' => $this->observaciones ?: null,
            ]
        );

        $proyecto->instalaciones()->sync($this->instalacionesSeleccionadas);

        $this->modalOpen = false;
        session()->flash('success', $this->proyectoId ? 'Proyecto actualizado.' : 'Proyecto creado con éxito.');
    }

    public bool $modalDeleteOpen = false;
    public ?int $proyectoToDeleteId = null;
    public string $proyectoToDeleteNombre = '';
    public bool $proyectoTieneRegistros = false;
    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function confirmarEliminar(int $id): void
    {
        $proy = Proyecto::withCount(['necesidadesServicio', 'movimientosEconomicos'])->findOrFail($id);
        $this->proyectoToDeleteId = $proy->id;
        $this->proyectoToDeleteNombre = $proy->nombre;
        $this->proyectoTieneRegistros = ($proy->necesidades_servicio_count > 0 || $proy->movimientos_economicos_count > 0);
        $this->feedbackMessage = null;
        $this->feedbackError = null;
        $this->modalDeleteOpen = true;
    }

    public function eliminar(): void
    {
        if (! $this->proyectoToDeleteId) {
            return;
        }

        try {
            $proy = Proyecto::withCount(['necesidadesServicio', 'movimientosEconomicos'])->findOrFail($this->proyectoToDeleteId);
            if ($this->proyectoTieneRegistros) {
                $proy->update(['estado' => 'cancelado']);
                $this->feedbackMessage = "El proyecto {$proy->nombre} cuenta con necesidades o registros contables; ha sido marcado como Cancelado para conservar su historial.";
            } else {
                $nombre = $proy->nombre;
                $proy->instalaciones()->detach();
                $proy->delete();
                $this->feedbackMessage = "Proyecto {$nombre} eliminado con éxito.";
            }

            $this->modalDeleteOpen = false;
            $this->reset(['proyectoToDeleteId', 'proyectoToDeleteNombre', 'proyectoTieneRegistros']);
            session()->flash('success', $this->feedbackMessage);
        } catch (\Throwable $e) {
            $this->modalDeleteOpen = false;
            $this->feedbackError = 'No se pudo eliminar el proyecto: ' . $e->getMessage();
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = Proyecto::with(['cliente', 'instalaciones', 'necesidadesServicio'])
            ->when($this->search, fn ($q) => $q->where('nombre', 'ilike', "%{$this->search}%"))
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('estado', $this->estadoFiltro))
            ->when($this->clienteFiltro, fn ($q) => $q->where('cliente_id', $this->clienteFiltro))
            ->orderBy('fecha_inicio', 'desc');

        $instalacionesCliente = $this->cliente_id
            ? Instalacion::where('cliente_id', $this->cliente_id)->where('activo', true)->get()
            : collect();

        return view('livewire.services.project-index', [
            'proyectos' => $query->paginate(10),
            'clientes' => Cliente::where('activo', true)->orderBy('razon_social')->get(),
            'instalacionesCliente' => $instalacionesCliente,
        ])->layout('layouts.app', ['title' => 'Gestión de Proyectos']);
    }
}
