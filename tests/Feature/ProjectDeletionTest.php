<?php

namespace Tests\Feature;

use App\Livewire\Services\ProjectIndex;
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

class ProjectDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_proyecto_con_turnos_se_cancela_sin_eliminar_su_historial(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Cavaleri',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Campaña Socorrismo',
            'fecha_inicio' => '2026-01-01',
            'estado' => 'activo',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => Carbon::parse('2026-06-15 08:00:00'),
            'hora_fin' => Carbon::parse('2026-06-15 12:00:00'),
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        Livewire::test(ProjectIndex::class)
            ->call('confirmarEliminar', $proyecto->id)
            ->call('eliminar');

        // El proyecto no debe desaparecer.
        $this->assertDatabaseHas('proyectos', [
            'id' => $proyecto->id,
            'estado' => 'cancelado',
        ]);

        // Sus turnos deben conservarse.
        $this->assertDatabaseHas('turnos', [
            'id' => $turno->id,
            'proyecto_id' => $proyecto->id,
        ]);
    }
}