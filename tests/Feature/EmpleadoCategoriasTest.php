<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpleadoCategoriasTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_puede_tener_varias_categorias_habilitadas(): void
    {
        $monitor = Categoria::create([
            'nombre' => 'Monitor',
            'codigo' => 'MON',
        ]);

        $socorrista = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Martín',
            'apellidos' => 'Prueba',
            'categoria_id' => $monitor->id,
            'activo' => true,
        ]);

        // Habilitar al empleado para ambas categorías.
        $empleado->categoriasHabilitadas()->sync([
            $monitor->id,
            $socorrista->id,
        ]);

        // Debe conservar su categoría contractual.
        $this->assertEquals(
            $monitor->id,
            $empleado->fresh()->categoria_id
        );

        // Debe tener ambas categorías habilitadas.
        $this->assertEqualsCanonicalizing(
            [$monitor->id, $socorrista->id],
            $empleado->categoriasHabilitadas()
                ->pluck('categorias.id')
                ->all()
        );

        // No debe crear relaciones duplicadas.
        $this->assertDatabaseCount('categoria_empleado', 2);
    }
}