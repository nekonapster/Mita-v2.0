<?php

namespace App\Livewire\Services;

use App\Models\Actividad;
use App\Models\Categoria;
use Livewire\Component;

class CatalogManager extends Component
{
    // Categorías
    public string $catNombre = '';
    public string $catCodigo = '';
    public string $catDescripcion = '';

    // Actividades
    public string $actNombre = '';
    public string $actDescripcion = '';

    public function guardarCategoria(): void
    {
        $this->validate([
            'catNombre' => 'required|string|max:100',
            'catCodigo' => 'required|string|max:20|unique:categorias,codigo',
            'catDescripcion' => 'nullable|string|max:300',
        ]);

        Categoria::create([
            'nombre' => $this->catNombre,
            'codigo' => strtoupper($this->catCodigo),
            'descripcion' => $this->catDescripcion ?: null,
            'activo' => true,
        ]);

        $this->reset(['catNombre', 'catCodigo', 'catDescripcion']);
        session()->flash('success', 'Categoría profesional creada.');
    }

    public function guardarActividad(): void
    {
        $this->validate([
            'actNombre' => 'required|string|max:100',
            'actDescripcion' => 'nullable|string|max:300',
        ]);

        Actividad::create([
            'nombre' => $this->actNombre,
            'descripcion' => $this->actDescripcion ?: null,
            'activo' => true,
        ]);

        $this->reset(['actNombre', 'actDescripcion']);
        session()->flash('success', 'Actividad creada.');
    }

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function eliminarCategoria(int $id): void
    {
        try {
            $cat = Categoria::withCount('empleados')->findOrFail($id);
            if ($cat->empleados_count > 0 || $cat->necesidadesServicio()->count() > 0) {
                $this->feedbackError = "No se puede eliminar la categoría '{$cat->nombre}' porque está vinculada a empleados o servicios. Desactívala en su lugar.";
                $this->feedbackMessage = null;
                return;
            }

            $nombre = $cat->nombre;
            $cat->delete();
            $this->feedbackMessage = "Categoría profesional '{$nombre}' eliminada correctamente.";
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar la categoría: ' . $e->getMessage();
        }
    }

    public function eliminarActividad(int $id): void
    {
        try {
            $act = Actividad::withCount('turnos')->findOrFail($id);
            if ($act->turnos_count > 0 || $act->necesidadesServicio()->count() > 0) {
                $this->feedbackError = "No se puede eliminar la actividad '{$act->nombre}' porque tiene turnos o coberturas vinculadas. Desactívala en su lugar.";
                $this->feedbackMessage = null;
                return;
            }

            $nombre = $act->nombre;
            $act->delete();
            $this->feedbackMessage = "Actividad '{$nombre}' eliminada correctamente.";
            $this->feedbackError = null;
        } catch (\Throwable $e) {
            $this->feedbackError = 'Error al eliminar la actividad: ' . $e->getMessage();
        }
    }

    public function toggleEstadoCategoria(int $id): void
    {
        $cat = Categoria::findOrFail($id);
        $cat->update(['activo' => ! $cat->activo]);
    }

    public function toggleEstadoActividad(int $id): void
    {
        $act = Actividad::findOrFail($id);
        $act->update(['activo' => ! $act->activo]);
    }

    public function render()
    {
        return view('livewire.services.catalog-manager', [
            'categorias' => Categoria::withCount('empleados')->orderBy('nombre')->get(),
            'actividades' => Actividad::withCount('turnos')->orderBy('nombre')->get(),
        ])->layout('layouts.app', ['title' => 'Catálogos']);
    }
}
