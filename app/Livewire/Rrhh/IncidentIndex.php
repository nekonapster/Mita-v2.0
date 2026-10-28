<?php

namespace App\Livewire\Rrhh;

use App\Models\Empleado;
use App\Models\Incidencia;
use App\Models\Instalacion;
use App\Models\Proyecto;
use Livewire\Component;
use Livewire\WithPagination;

class IncidentIndex extends Component
{
    use WithPagination;

    public string $estadoFiltro = '';
    public ?int $proyectoFiltro = null;
    public ?int $instalacionFiltro = null;

    public bool $modalCrearOpen = false;
    public string $titulo = '';
    public string $descripcion = '';
    public string $fecha = '';
    public ?int $empleado_id = null;
    public ?int $instalacion_id = null;
    public ?int $proyecto_id = null;
    public string $estado = 'abierta';

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['titulo', 'descripcion', 'empleado_id', 'instalacion_id', 'proyecto_id']);
        $this->fecha = now()->toDateString();
        $this->estado = 'abierta';
        $this->modalCrearOpen = true;
    }

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function guardarIncidencia(): void
    {
        $this->validate([
            'titulo' => 'required|string|max:150',
            'descripcion' => 'required|string|max:2000',
            'fecha' => 'required|date',
            'empleado_id' => 'nullable|exists:empleados,id',
            'instalacion_id' => 'nullable|exists:instalaciones,id',
            'proyecto_id' => 'nullable|exists:proyectos,id',
            'estado' => 'required|in:abierta,en_seguimiento,resuelta',
        ]);

        // Validación obligatoria: Vincular al menos a un empleado, una instalación o un proyecto
        if (! $this->empleado_id && ! $this->instalacion_id && ! $this->proyecto_id) {
            $this->addError('vinculacion', 'Una incidencia debe estar vinculada al menos a un empleado, una instalación o un proyecto.');
            return;
        }

        Incidencia::create([
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'fecha' => $this->fecha,
            'empleado_id' => $this->empleado_id ?: null,
            'instalacion_id' => $this->instalacion_id ?: null,
            'proyecto_id' => $this->proyecto_id ?: null,
            'estado' => $this->estado,
            'user_id' => auth()->id(),
        ]);

        $this->modalCrearOpen = false;
        $this->feedbackMessage = 'Incidencia registrada correctamente.';
        $this->feedbackError = null;
    }

    public function cambiarEstado(int $id, string $nuevoEstado): void
    {
        $inc = Incidencia::findOrFail($id);
        $inc->update(['estado' => $nuevoEstado]);
        $this->feedbackMessage = "Estado de la incidencia actualizado a '{$nuevoEstado}'.";
        $this->feedbackError = null;
    }

    public function eliminar(int $id): void
    {
        try {
            $inc = Incidencia::findOrFail($id);
            $titulo = $inc->titulo;
            $inc->delete();

            $this->feedbackMessage = "Incidencia '{$titulo}' eliminada correctamente.";
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar la incidencia: ' . $e->getMessage();
            $this->feedbackMessage = null;
        }
    }

    public function render()
    {
        $query = Incidencia::with(['empleado', 'instalacion', 'proyecto', 'creador'])
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('estado', $this->estadoFiltro))
            ->when($this->proyectoFiltro, fn ($q) => $q->where('proyecto_id', $this->proyectoFiltro))
            ->when($this->instalacionFiltro, fn ($q) => $q->where('instalacion_id', $this->instalacionFiltro))
            ->orderBy('fecha', 'desc');

        return view('livewire.rrhh.incident-index', [
            'incidencias' => $query->paginate(10),
            'empleados' => Empleado::where('activo', true)->orderBy('nombre')->get(),
            'instalaciones' => Instalacion::where('activo', true)->orderBy('nombre')->get(),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Gestión de Incidencias']);
    }
}
