<?php

namespace App\Livewire\Economics;

use App\Models\RegistroHora;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class TimeEntryValidation extends Component
{
    use WithPagination;

    public string $estadoFiltro = 'pendiente_revision'; // pendiente_revision, validado, ''
    public string $fechaDesde = '';
    public string $fechaHasta = '';

    // Edición rápida
    public bool $modalEditOpen = false;
    public ?int $registroId = null;
    public string $editInicioReal = '';
    public string $editFinReal = '';
    public int $editDescansoMinutos = 0;
    public string $editObservaciones = '';

    public function mount(): void
    {
        $this->fechaDesde = now()->subWeeks(2)->toDateString();
        $this->fechaHasta = now()->toDateString();
    }

    public function validar(int $id): void
    {
        $registro = RegistroHora::findOrFail($id);
        $registro->update([
            'estado' => RegistroHora::ESTADO_VALIDADO,
            'validado_por' => auth()->id(),
            'validado_en' => now(),
        ]);

        session()->flash('success', 'Registro de horas validado correctamente.');
    }

    public function abrirModalEditar(int $id): void
    {
        $registro = RegistroHora::findOrFail($id);
        $this->registroId = $registro->id;
        $this->editInicioReal = Carbon::parse($registro->inicio_real)->format('Y-m-d\TH:i');
        $this->editFinReal = Carbon::parse($registro->fin_real)->format('Y-m-d\TH:i');
        $this->editDescansoMinutos = $registro->descanso_minutos;
        $this->editObservaciones = $registro->observaciones ?? '';
        $this->modalEditOpen = true;
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editInicioReal' => 'required|date',
            'editFinReal' => 'required|date|after_or_equal:editInicioReal',
            'editDescansoMinutos' => 'required|integer|min:0',
            'editObservaciones' => 'nullable|string|max:500',
        ]);

        $horasComputables = RegistroHora::calcularHoras(
            $this->editInicioReal,
            $this->editFinReal,
            $this->editDescansoMinutos
        );

        $registro = RegistroHora::findOrFail($this->registroId);
        $registro->update([
            'inicio_real' => $this->editInicioReal,
            'fin_real' => $this->editFinReal,
            'descanso_minutos' => $this->editDescansoMinutos,
            'horas_computables' => $horasComputables,
            'observaciones' => $this->editObservaciones ?: null,
            'modificado_por' => auth()->id(),
        ]);

        $this->modalEditOpen = false;
        session()->flash('success', 'Horas realizadas corregidas y recalculadas.');
    }

    public function render()
    {
        $query = RegistroHora::with([
            'asignacion.turno.instalacion.cliente',
            'asignacion.turno.actividad',
            'asignacion.empleado',
            'validador',
        ])
        ->when($this->estadoFiltro !== '', fn ($q) => $q->where('estado', $this->estadoFiltro))
        ->whereHas('asignacion.turno', function ($q) {
            if ($this->fechaDesde) {
                $q->where('fecha', '>=', $this->fechaDesde);
            }
            if ($this->fechaHasta) {
                $q->where('fecha', '<=', $this->fechaHasta);
            }
        })
        ->orderBy('created_at', 'desc');

        return view('livewire.economics.time-entry-validation', [
            'registros' => $query->paginate(15),
        ])->layout('layouts.app', ['title' => 'Validación de Horas']);
    }
}
