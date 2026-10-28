<?php

namespace App\Livewire\Economics;

use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Services\EconomicCalculationService;
use Livewire\Component;

class EconomicsDashboard extends Component
{
    public ?int $proyectoSeleccionado = null;
    public ?int $instalacionSeleccionada = null;
    public string $fechaDesde = '';
    public string $fechaHasta = '';

    public function mount(?int $proyectoSeleccionado = null): void
    {
        $this->proyectoSeleccionado = $proyectoSeleccionado ?: Proyecto::first()?->id;
        $this->fechaDesde = now()->startOfYear()->toDateString();
        $this->fechaHasta = now()->endOfYear()->toDateString();
    }

    public function render()
    {
        $proyecto = $this->proyectoSeleccionado ? Proyecto::with('instalaciones')->find($this->proyectoSeleccionado) : null;
        $informe = null;

        if ($proyecto) {
            $econService = new EconomicCalculationService();
            $informe = $econService->calcularInformeProyecto(
                $proyecto,
                $this->fechaDesde ?: null,
                $this->fechaHasta ?: null,
                $this->instalacionSeleccionada ?: null
            );
        }

        $instalacionesDelProyecto = $proyecto?->instalaciones ?? collect();

        return view('livewire.economics.economics-dashboard', [
            'proyectos' => Proyecto::orderBy('nombre')->get(),
            'instalacionesDelProyecto' => $instalacionesDelProyecto,
            'proyecto' => $proyecto,
            'informe' => $informe,
        ])->layout('layouts.app', ['title' => 'Control Económico e Informes']);
    }
}
