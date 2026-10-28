<?php

namespace App\Livewire\Economics;

use App\Models\Instalacion;
use App\Models\MovimientoEconomico;
use App\Models\Proyecto;
use Livewire\Component;
use Livewire\WithPagination;

class EconomicMovementsManager extends Component
{
    use WithPagination;

    public ?int $proyectoFiltro = null;
    public string $tipoFiltro = ''; // 'ingreso', 'gasto', ''

    public bool $modalOpen = false;
    public ?int $movimientoId = null;
    public ?int $proyecto_id = null;
    public ?int $instalacion_id = null;
    public string $tipo = 'ingreso';
    public string $fecha = '';
    public string $concepto = '';
    public float $importe = 0.0;
    public string $observaciones = '';

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->proyecto_id = Proyecto::first()?->id;
    }

    public function abrirModalCrear(): void
    {
        $this->reset(['movimientoId', 'instalacion_id', 'concepto', 'observaciones']);
        $this->proyecto_id = $this->proyectoFiltro ?: Proyecto::first()?->id;
        $this->tipo = 'ingreso';
        $this->fecha = now()->toDateString();
        $this->importe = 0.0;
        $this->modalOpen = true;
    }

    public function abrirModalEditar(int $id): void
    {
        $mov = MovimientoEconomico::findOrFail($id);
        $this->movimientoId = $mov->id;
        $this->proyecto_id = $mov->proyecto_id;
        $this->instalacion_id = $mov->instalacion_id;
        $this->tipo = $mov->tipo;
        $this->fecha = $mov->fecha->toDateString();
        $this->concepto = $mov->concepto;
        $this->importe = (float) $mov->importe;
        $this->observaciones = $mov->observaciones ?? '';
        $this->modalOpen = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'proyecto_id' => 'required|exists:proyectos,id',
            'instalacion_id' => 'nullable|exists:instalaciones,id',
            'tipo' => 'required|in:ingreso,gasto',
            'fecha' => 'required|date',
            'concepto' => 'required|string|max:150',
            'importe' => 'required|numeric|min:0.01',
            'observaciones' => 'nullable|string|max:500',
        ]);

        MovimientoEconomico::updateOrCreate(
            ['id' => $this->movimientoId],
            [
                'proyecto_id' => $this->proyecto_id,
                'instalacion_id' => $this->instalacion_id ?: null,
                'tipo' => $this->tipo,
                'fecha' => $this->fecha,
                'concepto' => $this->concepto,
                'importe' => $this->importe,
                'observaciones' => $this->observaciones ?: null,
                'registrado_por' => auth()->id(),
            ]
        );

        $this->modalOpen = false;
        session()->flash('success', $this->movimientoId ? 'Movimiento actualizado.' : 'Movimiento registrado con éxito.');
    }

    public ?int $movimientoToDeleteId = null;
    public bool $modalDeleteOpen = false;
    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function eliminar(int $id): void
    {
        $this->confirmarEliminar($id);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->movimientoToDeleteId = $id;
        $this->modalDeleteOpen = true;
        $this->feedbackMessage = null;
        $this->feedbackError = null;
    }

    public function ejecutarEliminar(): void
    {
        if (! $this->movimientoToDeleteId) {
            return;
        }

        try {
            $mov = MovimientoEconomico::findOrFail($this->movimientoToDeleteId);
            $mov->delete();
            $this->modalDeleteOpen = false;
            $this->movimientoToDeleteId = null;
            $this->feedbackMessage = 'Movimiento económico eliminado con éxito.';
            $this->feedbackError = null;
            session()->flash('success', 'Movimiento eliminado.');
        } catch (\Throwable $e) {
            $this->modalDeleteOpen = false;
            $this->feedbackError = 'No se pudo eliminar el movimiento económico seleccionado.';
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = MovimientoEconomico::with(['proyecto', 'instalacion', 'registrador'])
            ->when($this->proyectoFiltro, fn ($q) => $q->where('proyecto_id', $this->proyectoFiltro))
            ->when($this->tipoFiltro !== '', fn ($q) => $q->where('tipo', $this->tipoFiltro))
            ->orderBy('fecha', 'desc');

        $instalacionesDelProyecto = $this->proyecto_id
            ? Proyecto::find($this->proyecto_id)?->instalaciones ?? collect()
            : collect();

        return view('livewire.economics.economic-movements-manager', [
            'movimientos' => $query->paginate(15),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
            'instalacionesDelProyecto' => $instalacionesDelProyecto,
        ])->layout('layouts.app', ['title' => 'Ingresos y Gastos']);
    }
}
