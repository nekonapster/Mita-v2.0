<?php

namespace Tests\Feature;

use App\Livewire\Services\ProjectIndex;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectInstallationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_proyecto_no_admite_instalaciones_de_otro_cliente(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $clienteA = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'Ayuntamiento de Tomares',
        ]);

        $instalacionAjena = Instalacion::create([
            'cliente_id' => $clienteB->id,
            'nombre' => 'Piscina Tomares',
        ]);

        Livewire::actingAs($admin)
            ->test(ProjectIndex::class)
            ->set('cliente_id', $clienteA->id)
            ->set('nombre', 'Piscinas de verano')
            ->set('fecha_inicio', '2027-06-01')
            ->set('estado', 'borrador')
            ->set('instalacionesSeleccionadas', [
                $instalacionAjena->id,
            ])
            ->call('guardarProyecto')
            ->assertHasErrors(['instalacionesSeleccionadas.0']);

        // No debe crearse el proyecto.
        $this->assertDatabaseCount('proyectos', 0);
    }
    public function test_proyecto_admite_varias_instalaciones_del_mismo_cliente(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $cavaleri = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Cavaleri',
        ]);

        $franciscoLeon = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Francisco León',
        ]);

        Livewire::actingAs($admin)
            ->test(ProjectIndex::class)
            ->set('cliente_id', $cliente->id)
            ->set('nombre', 'Piscinas de verano')
            ->set('fecha_inicio', '2027-06-01')
            ->set('estado', 'borrador')
            ->set('instalacionesSeleccionadas', [
                $cavaleri->id,
                $franciscoLeon->id,
            ])
            ->call('guardarProyecto')
            ->assertHasNoErrors();

        $proyecto = Proyecto::where('nombre', 'Piscinas de verano')->firstOrFail();

        $this->assertEquals($cliente->id, $proyecto->cliente_id);

        $this->assertEqualsCanonicalizing(
            [$cavaleri->id, $franciscoLeon->id],
            $proyecto->instalaciones()->pluck('instalaciones.id')->all()
        );
    }
}
