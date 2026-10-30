<?php

namespace App\Livewire\Economics;

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\TarifaCoste;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;

class CostRateManager extends Component
{
    use WithPagination;

    public string $tipoFiltro = ''; // 'empleado', 'categoria', ''

    // Modal Crear / Editar
    public bool $modalOpen = false;
    public ?int $tarifaId = null;
    public string $tipoAsignacion = 'categoria'; // 'categoria' o 'empleado'
    public ?int $categoria_id = null;
    public ?int $empleado_id = null;
    public float $coste_hora = 15.0;
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $observaciones = '';

    public function mount(): void
    {
        $this->fecha_inicio = now()->toDateString();
        $this->categoria_id = Categoria::first()?->id;
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['tarifaId', 'observaciones', 'fecha_fin', 'empleado_id']);
        $this->tipoAsignacion = 'categoria';
        $this->categoria_id = Categoria::first()?->id;
        $this->fecha_inicio = now()->toDateString();
        $this->coste_hora = 15.0;
        $this->modalOpen = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $tarifa = TarifaCoste::findOrFail($id);
        $this->tarifaId = $tarifa->id;
        $this->tipoAsignacion = $tarifa->empleado_id ? 'empleado' : 'categoria';
        $this->categoria_id = $tarifa->categoria_id;
        $this->empleado_id = $tarifa->empleado_id;
        $this->coste_hora = (float) $tarifa->coste_hora;
        $this->fecha_inicio = $tarifa->fecha_inicio->toDateString();
        $this->fecha_fin = $tarifa->fecha_fin ? $tarifa->fecha_fin->toDateString() : '';
        $this->observaciones = $tarifa->observaciones ?? '';
        $this->modalOpen = true;
    }

    public function guardar(): void
    {
        // Comprobar permisos antes de validar o guardar.
        if ($this->tarifaId !== null) {
            // Estamos editando una tarifa existente.
            $tarifa = TarifaCoste::findOrFail($this->tarifaId);

            Gate::authorize('update', $tarifa);
        } else {
            // Estamos creando una tarifa nueva.
            Gate::authorize('create', TarifaCoste::class);
        }

        $this->validate([
            'tipoAsignacion' => 'required|in:categoria,empleado',
            'categoria_id' => 'required_if:tipoAsignacion,categoria|nullable|exists:categorias,id',
            'empleado_id' => 'required_if:tipoAsignacion,empleado|nullable|exists:empleados,id',
            'coste_hora' => 'required|numeric|min:0.01|max:999.99',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'observaciones' => 'nullable|string|max:500',
        ], [
            'fecha_fin.after_or_equal' => 'La fecha de fin de vigencia no puede ser anterior a la de inicio.',
        ]);
        $existeConflicto = app(
            \App\Services\CostRatePeriodService::class
        )->existeSolapamiento(
            $this->tipoAsignacion === 'empleado'
                ? (int) $this->empleado_id
                : null,

            $this->tipoAsignacion === 'categoria'
                ? (int) $this->categoria_id
                : null,

            $this->fecha_inicio,
            $this->fecha_fin ?: null,
            $this->tarifaId
        );

        if ($existeConflicto) {
            $this->addError(
                'fecha_inicio',
                'Ya existe una tarifa para este empleado o categoría '
                    . 'con un período de vigencia que se solapa.'
            );

            return;
        }
        TarifaCoste::updateOrCreate(
            ['id' => $this->tarifaId],
            [
                'categoria_id' => $this->tipoAsignacion === 'categoria' ? $this->categoria_id : null,
                'empleado_id' => $this->tipoAsignacion === 'empleado' ? $this->empleado_id : null,
                'coste_hora' => $this->coste_hora,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin ?: null,
                'observaciones' => $this->observaciones ?: null,
            ]
        );

        $this->modalOpen = false;
        session()->flash('success', $this->tarifaId ? 'Tarifa actualizada correctamente.' : 'Tarifa registrada con éxito.');
    }

    public ?int $tarifaToDeleteId = null;
    public bool $modalDeleteOpen = false;
    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function eliminar(int $id): void
    {
        $this->confirmarEliminar($id);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->tarifaToDeleteId = $id;
        $this->modalDeleteOpen = true;
        $this->feedbackMessage = null;
        $this->feedbackError = null;
    }

    public function ejecutarEliminar(): void
    {
        if (! $this->tarifaToDeleteId) {
            return;
        }
        // Recuperar la tarifa que se intenta eliminar.
        $tarifa = TarifaCoste::findOrFail($this->tarifaToDeleteId);

        // Comprobar autorización antes de eliminar.
        Gate::authorize('delete', $tarifa);
        try {
            $tarifa = TarifaCoste::findOrFail($this->tarifaToDeleteId);
            $tarifa->delete();
            $this->modalDeleteOpen = false;
            $this->tarifaToDeleteId = null;
            $this->feedbackMessage = 'Tarifa de coste eliminada con éxito.';
            $this->feedbackError = null;
            session()->flash('success', 'Tarifa de coste eliminada con éxito.');
        } catch (\Throwable $e) {
            $this->modalDeleteOpen = false;
            $this->feedbackError = 'No se puede eliminar la tarifa porque está asociada a cálculos o registros existentes.';
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = TarifaCoste::with(['empleado', 'categoria'])
            ->when($this->tipoFiltro === 'empleado', fn($q) => $q->whereNotNull('empleado_id'))
            ->when($this->tipoFiltro === 'categoria', fn($q) => $q->whereNotNull('categoria_id'))
            ->orderBy('fecha_inicio', 'desc');

        return view('livewire.economics.cost-rate-manager', [
            'tarifas' => $query->paginate(15),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
            'empleados' => Empleado::where('activo', true)->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Tarifas de Coste']);
    }


}
