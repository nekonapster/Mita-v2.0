<?php

namespace Tests\Feature;

use App\Livewire\Services\CatalogManager;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_elimina_categoria_vinculada_a_un_turno(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente de prueba',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina municipal',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto de prueba',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-11-16',
            'hora_inicio' => Carbon::parse('2026-11-16 08:00', 'Europe/Madrid'),
            'hora_fin' => Carbon::parse('2026-11-16 16:00', 'Europe/Madrid'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        Livewire::test(CatalogManager::class)
            ->call('eliminarCategoria', $categoria->id)
            ->assertSet('feedbackError', function ($mensaje) {
                return is_string($mensaje)
                    && str_contains($mensaje, 'Desactívala');
            });

        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
        ]);

        $this->assertDatabaseHas('turnos', [
            'id' => $turno->id,
            'categoria_id' => $categoria->id,
        ]);
    }
}