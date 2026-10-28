<?php

namespace App\Livewire\Rrhh;

use App\Models\Categoria;
use App\Models\Empleado;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoriaFiltro = '';
    public string $estadoFiltro = '1'; // 1 = activos, 0 = inactivos, '' = todos

    public bool $modalOpen = false;
    public ?int $empleadoId = null;
    public string $nombre = '';
    public string $apellidos = '';
    public string $dni_nie = '';
    public string $email = '';
    public string $telefono = '';
    public ?int $categoria_id = null;
    public bool $activo = true;
    public string $observaciones = '';

    protected function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'dni_nie' => 'nullable|string|max:20|unique:empleados,dni_nie,' . $this->empleadoId,
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'categoria_id' => 'required|exists:categorias,id',
            'activo' => 'boolean',
            'observaciones' => 'nullable|string|max:1000',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['empleadoId', 'nombre', 'apellidos', 'dni_nie', 'email', 'telefono', 'categoria_id', 'observaciones']);
        $this->activo = true;
        $this->categoria_id = Categoria::first()?->id;
        $this->modalOpen = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $empleado = Empleado::findOrFail($id);
        $this->empleadoId = $empleado->id;
        $this->nombre = $empleado->nombre;
        $this->apellidos = $empleado->apellidos;
        $this->dni_nie = $empleado->dni_nie ?? '';
        $this->email = $empleado->email ?? '';
        $this->telefono = $empleado->telefono ?? '';
        $this->categoria_id = $empleado->categoria_id;
        $this->activo = (bool) $empleado->activo;
        $this->observaciones = $empleado->observaciones ?? '';
        $this->modalOpen = true;
    }

    public function guardar(): void
    {
        $this->validate();

        Empleado::updateOrCreate(
            ['id' => $this->empleadoId],
            [
                'nombre' => $this->nombre,
                'apellidos' => $this->apellidos,
                'dni_nie' => $this->dni_nie ?: null,
                'email' => $this->email ?: null,
                'telefono' => $this->telefono ?: null,
                'categoria_id' => $this->categoria_id,
                'activo' => $this->activo,
                'observaciones' => $this->observaciones ?: null,
            ]
        );

        $this->modalOpen = false;
        session()->flash('success', $this->empleadoId ? 'Empleado actualizado correctamente.' : 'Empleado creado con éxito.');
    }

    public bool $modalDeleteOpen = false;
    public ?int $empleadoToDeleteId = null;
    public string $empleadoToDeleteNombre = '';
    public bool $tieneHistorialLaboral = false;
    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function confirmarEliminar(int $id): void
    {
        $emp = Empleado::withCount(['contratos', 'asignaciones', 'ausencias', 'incidencias'])->findOrFail($id);
        $this->empleadoToDeleteId = $emp->id;
        $this->empleadoToDeleteNombre = $emp->nombre_completo;
        $this->tieneHistorialLaboral = ($emp->contratos_count > 0 || $emp->asignaciones_count > 0 || $emp->ausencias_count > 0 || $emp->incidencias_count > 0);
        $this->feedbackMessage = null;
        $this->feedbackError = null;
        $this->modalDeleteOpen = true;
    }

    public function eliminar(): void
    {
        if (! $this->empleadoToDeleteId) {
            return;
        }

        try {
            $emp = Empleado::findOrFail($this->empleadoToDeleteId);

            if ($this->tieneHistorialLaboral) {
                $emp->update(['activo' => false]);
                $this->feedbackMessage = "El empleado {$emp->nombre_completo} cuenta con historial de contratos/turnos; ha sido dado de baja (marcado como Inactivo) para preservar la integridad laboral.";
            } else {
                $nombre = $emp->nombre_completo;
                $emp->delete();
                $this->feedbackMessage = "Empleado {$nombre} eliminado correctamente.";
            }

            $this->modalDeleteOpen = false;
            $this->reset(['empleadoToDeleteId', 'empleadoToDeleteNombre', 'tieneHistorialLaboral']);
            session()->flash('success', $this->feedbackMessage);
        } catch (\Throwable $e) {
            $this->modalDeleteOpen = false;
            $this->feedbackError = 'No se pudo eliminar el empleado: ' . $e->getMessage();
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = Empleado::with(['categoria', 'contratos'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nombre', 'ilike', "%{$this->search}%")
                        ->orWhere('apellidos', 'ilike', "%{$this->search}%")
                        ->orWhere('dni_nie', 'ilike', "%{$this->search}%")
                        ->orWhere('email', 'ilike', "%{$this->search}%");
                });
            })
            ->when($this->categoriaFiltro !== '', fn ($q) => $q->where('categoria_id', $this->categoriaFiltro))
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('activo', (bool) $this->estadoFiltro))
            ->orderBy('apellidos')
            ->orderBy('nombre');

        return view('livewire.rrhh.employee-index', [
            'empleados' => $query->paginate(10),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Gestión de Empleados']);
    }
}
