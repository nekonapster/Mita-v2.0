<?php

namespace App\Livewire\Services;

use App\Models\Proyecto;
use App\Services\EconomicCalculationService;
use Livewire\Component;

class ProjectShow extends Component
{
    public Proyecto $proyecto;

    public function mount(int $id): void
    {
        $this->proyecto = Proyecto::with([
            'cliente',
            'instalaciones',
            'necesidadesServicio.actividad',
            'necesidadesServicio.categoria',
            'necesidadesServicio.instalacion',
            'movimientosEconomicos',
        ])->findOrFail($id);
    }

    public function render()
    {
        $econService = new EconomicCalculationService();
        $informe = $econService->calcularInformeProyecto($this->proyecto);

        return view('livewire.services.project-show', [
            'informe' => $informe,
        ])->layout('layouts.app', ['title' => 'Proyecto: ' . $this->proyecto->nombre]);
    }
}
