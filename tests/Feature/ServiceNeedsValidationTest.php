<?php

namespace Tests\Feature;

use App\Livewire\Services\ServiceNeedsManager;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\NecesidadServicio;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceNeedsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_necesidad_no_admite_instalacion_ajena_al_proyecto(): void
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

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscinas de verano',
            'fecha_inicio' => '2027-06-01',
        ]);

        $instalacionA = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Cavaleri',
        ]);

        $instalacionB = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Francisco León',
        ]);

        // Solo Cavaleri está asociada al proyecto.
        $proyecto->instalaciones()->attach($instalacionA->id);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        Livewire::actingAs($admin)
            ->test(ServiceNeedsManager::class)
            ->set('proyecto_id', $proyecto->id)
            ->set('instalacion_id', $instalacionB->id)
            ->set('actividad_id', $actividad->id)
            ->set('categoria_id', $categoria->id)
            ->set('fecha_inicio_vigencia', '2027-06-01')
            ->set('fecha_fin_vigencia', '2027-06-30')
            ->set('dias_semana', [1, 2, 3, 4, 5])
            ->set('hora_inicio', '12:15')
            ->set('hora_fin', '20:45')
            ->set('plazas_necesarias', 1)
            ->call('guardar')
            ->assertHasErrors(['instalacion_id']);

        $this->assertDatabaseCount('necesidades_servicio', 0);
    }
    public function test_necesidad_admite_instalacion_asociada_al_proyecto(): void
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

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscinas de verano',
        'fecha_inicio' => '2027-06-01',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Cavaleri',
    ]);

    // La instalación pertenece al proyecto.
    $proyecto->instalaciones()->attach($instalacion->id);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $categoria = Categoria::create([
        'nombre' => 'Socorrista',
        'codigo' => 'SOC',
    ]);

    Livewire::actingAs($admin)
        ->test(ServiceNeedsManager::class)
        ->set('proyecto_id', $proyecto->id)
        ->set('instalacion_id', $instalacion->id)
        ->set('actividad_id', $actividad->id)
        ->set('categoria_id', $categoria->id)
        ->set('fecha_inicio_vigencia', '2027-06-01')
        ->set('fecha_fin_vigencia', '2027-06-30')
        ->set('dias_semana', [1, 2, 3, 4, 5])
        ->set('hora_inicio', '12:15')
        ->set('hora_fin', '20:45')
        ->set('plazas_necesarias', 1)
        ->call('guardar')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('necesidades_servicio', [
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'plazas_necesarias' => 1,
    ]);
}

}