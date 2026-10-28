<?php

namespace App\Livewire\Services;

use App\Models\Cliente;
use App\Models\Instalacion;
use Livewire\Component;
use Livewire\WithPagination;

class ClientIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $estadoFiltro = '1';

    // Modal Cliente
    public bool $modalClienteOpen = false;
    public ?int $clienteId = null;
    public string $razon_social = '';
    public string $nif_cif = '';
    public string $persona_contacto = '';
    public string $email = '';
    public string $telefono = '';
    public string $direccion = '';
    public bool $activo = true;
    public string $observaciones = '';

    // Modal Instalación
    public bool $modalInstalacionOpen = false;
    public ?int $instalacionClienteId = null;
    public string $instalacion_nombre = '';
    public string $instalacion_direccion = '';
    public string $instalacion_contacto = '';
    public string $instalacion_telefono = '';
    public string $instalacion_observaciones = '';

    public function abrirModalCliente(?int $id = null): void
    {
        $this->reset(['clienteId', 'razon_social', 'nif_cif', 'persona_contacto', 'email', 'telefono', 'direccion', 'observaciones']);
        $this->activo = true;

        if ($id) {
            $cliente = Cliente::findOrFail($id);
            $this->clienteId = $cliente->id;
            $this->razon_social = $cliente->razon_social;
            $this->nif_cif = $cliente->nif_cif ?? '';
            $this->persona_contacto = $cliente->persona_contacto ?? '';
            $this->email = $cliente->email ?? '';
            $this->telefono = $cliente->telefono ?? '';
            $this->direccion = $cliente->direccion ?? '';
            $this->activo = (bool) $cliente->activo;
            $this->observaciones = $cliente->observaciones ?? '';
        }

        $this->modalClienteOpen = true;
    }

    public function guardarCliente(): void
    {
        $this->validate([
            'razon_social' => 'required|string|max:150',
            'nif_cif' => 'nullable|string|max:30',
            'persona_contacto' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:300',
            'activo' => 'boolean',
            'observaciones' => 'nullable|string|max:500',
        ]);

        Cliente::updateOrCreate(
            ['id' => $this->clienteId],
            [
                'razon_social' => $this->razon_social,
                'nif_cif' => $this->nif_cif ?: null,
                'persona_contacto' => $this->persona_contacto ?: null,
                'email' => $this->email ?: null,
                'telefono' => $this->telefono ?: null,
                'direccion' => $this->direccion ?: null,
                'activo' => $this->activo,
                'observaciones' => $this->observaciones ?: null,
            ]
        );

        $this->modalClienteOpen = false;
        session()->flash('success', $this->clienteId ? 'Cliente actualizado.' : 'Cliente creado correctamente.');
    }

    public function abrirModalInstalacion(int $clienteId): void
    {
        $this->reset(['instalacion_nombre', 'instalacion_direccion', 'instalacion_contacto', 'instalacion_telefono', 'instalacion_observaciones']);
        $this->instalacionClienteId = $clienteId;
        $this->modalInstalacionOpen = true;
    }

    public function guardarInstalacion(): void
    {
        $this->validate([
            'instalacion_nombre' => 'required|string|max:150',
            'instalacion_direccion' => 'nullable|string|max:300',
            'instalacion_contacto' => 'nullable|string|max:100',
            'instalacion_telefono' => 'nullable|string|max:30',
            'instalacion_observaciones' => 'nullable|string|max:500',
        ]);

        Instalacion::create([
            'cliente_id' => $this->instalacionClienteId,
            'nombre' => $this->instalacion_nombre,
            'direccion' => $this->instalacion_direccion ?: null,
            'persona_contacto' => $this->instalacion_contacto ?: null,
            'telefono' => $this->instalacion_telefono ?: null,
            'observaciones' => $this->instalacion_observaciones ?: null,
            'activo' => true,
        ]);

        $this->modalInstalacionOpen = false;
        session()->flash('success', 'Instalación añadida al cliente.');
    }

    public bool $modalDeleteClienteOpen = false;
    public ?int $clienteToDeleteId = null;
    public string $clienteToDeleteNombre = '';
    public bool $clienteTieneRegistros = false;

    public bool $modalDeleteInstalacionOpen = false;
    public ?int $instalacionToDeleteId = null;
    public string $instalacionToDeleteNombre = '';
    public bool $instalacionTieneRegistros = false;

    public ?string $feedbackMessage = null;
    public ?string $feedbackError = null;

    public function confirmarEliminarCliente(int $id): void
    {
        $c = Cliente::withCount(['instalaciones', 'proyectos'])->findOrFail($id);
        $this->clienteToDeleteId = $c->id;
        $this->clienteToDeleteNombre = $c->razon_social;
        $this->clienteTieneRegistros = ($c->instalaciones_count > 0 || $c->proyectos_count > 0);
        $this->feedbackMessage = null;
        $this->feedbackError = null;
        $this->modalDeleteClienteOpen = true;
    }

    public function eliminarCliente(): void
    {
        if (! $this->clienteToDeleteId) {
            return;
        }

        try {
            $cliente = Cliente::withCount(['instalaciones', 'proyectos'])->findOrFail($this->clienteToDeleteId);
            if ($this->clienteTieneRegistros) {
                $cliente->update(['activo' => false]);
                $this->feedbackMessage = "El cliente {$cliente->razon_social} cuenta con instalaciones o proyectos vinculados; ha sido desactivado para proteger el historial.";
            } else {
                $nombre = $cliente->razon_social;
                $cliente->delete();
                $this->feedbackMessage = "Cliente {$nombre} eliminado con éxito.";
            }

            $this->modalDeleteClienteOpen = false;
            $this->reset(['clienteToDeleteId', 'clienteToDeleteNombre', 'clienteTieneRegistros']);
            session()->flash('success', $this->feedbackMessage);
        } catch (\Throwable $e) {
            $this->modalDeleteClienteOpen = false;
            $this->feedbackError = 'No se pudo eliminar el cliente: ' . $e->getMessage();
            session()->flash('error', $this->feedbackError);
        }
    }

    public function confirmarEliminarInstalacion(int $id): void
    {
        $inst = Instalacion::withCount(['turnos', 'necesidadesServicio'])->findOrFail($id);
        $this->instalacionToDeleteId = $inst->id;
        $this->instalacionToDeleteNombre = $inst->nombre;
        $this->instalacionTieneRegistros = ($inst->turnos_count > 0 || $inst->necesidades_servicio_count > 0);
        $this->feedbackMessage = null;
        $this->feedbackError = null;
        $this->modalDeleteInstalacionOpen = true;
    }

    public function eliminarInstalacion(): void
    {
        if (! $this->instalacionToDeleteId) {
            return;
        }

        try {
            $inst = Instalacion::withCount(['turnos', 'necesidadesServicio'])->findOrFail($this->instalacionToDeleteId);
            if ($this->instalacionTieneRegistros) {
                $inst->update(['activo' => false]);
                $this->feedbackMessage = "La instalación {$inst->nombre} cuenta con turnos o necesidades; ha sido desactivada para salvaguardar la planificación.";
            } else {
                $nombre = $inst->nombre;
                $inst->delete();
                $this->feedbackMessage = "Instalación {$nombre} eliminada correctamente.";
            }

            $this->modalDeleteInstalacionOpen = false;
            $this->reset(['instalacionToDeleteId', 'instalacionToDeleteNombre', 'instalacionTieneRegistros']);
            session()->flash('success', $this->feedbackMessage);
        } catch (\Throwable $e) {
            $this->modalDeleteInstalacionOpen = false;
            $this->feedbackError = 'No se pudo eliminar la instalación: ' . $e->getMessage();
            session()->flash('error', $this->feedbackError);
        }
    }

    public function render()
    {
        $query = Cliente::with(['instalaciones', 'proyectos'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('razon_social', 'ilike', "%{$this->search}%")
                        ->orWhere('nif_cif', 'ilike', "%{$this->search}%")
                        ->orWhere('persona_contacto', 'ilike', "%{$this->search}%");
                });
            })
            ->when($this->estadoFiltro !== '', fn ($q) => $q->where('activo', (bool) $this->estadoFiltro))
            ->orderBy('razon_social');

        return view('livewire.services.client-index', [
            'clientes' => $query->paginate(10),
        ])->layout('layouts.app', ['title' => 'Clientes e Instalaciones']);
    }
}
