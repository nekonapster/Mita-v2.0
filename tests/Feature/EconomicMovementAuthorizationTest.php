<?php

namespace Tests\Feature;

use App\Livewire\Economics\EconomicMovementsManager;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\MovimientoEconomico;

class EconomicMovementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_no_puede_crear_movimiento_economico(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Prueba',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Prueba',
            'fecha_inicio' => '2026-01-01',
        ]);

        Livewire::actingAs($empleado)
            ->test(EconomicMovementsManager::class)
            ->set('proyecto_id', $proyecto->id)
            ->set('tipo', 'gasto')
            ->set('fecha', '2026-06-15')
            ->set('concepto', 'Material de piscina')
            ->set('importe', 150)
            ->call('guardar')
            ->assertForbidden();

        $this->assertDatabaseMissing('movimientos_economicos', [
            'proyecto_id' => $proyecto->id,
            'concepto' => 'Material de piscina',
        ]);
    }

    public function test_empleado_no_puede_editar_movimiento_economico(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Prueba',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Prueba',
            'fecha_inicio' => '2026-01-01',
        ]);

        $movimiento = MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => 'gasto',
            'fecha' => '2026-06-15',
            'concepto' => 'Material de piscina',
            'importe' => 150,
        ]);

        Livewire::actingAs($empleado)
            ->test(EconomicMovementsManager::class)
            ->set('movimientoId', $movimiento->id)
            ->set('proyecto_id', $proyecto->id)
            ->set('tipo', 'gasto')
            ->set('fecha', '2026-06-15')
            ->set('concepto', 'Material modificado')
            ->set('importe', 999)
            ->call('guardar')
            ->assertForbidden();

        // El movimiento debe conservar sus valores originales.
        $this->assertDatabaseHas('movimientos_economicos', [
            'id' => $movimiento->id,
            'concepto' => 'Material de piscina',
            'importe' => 150,
        ]);
    }
    public function test_empleado_no_puede_eliminar_movimiento_economico(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Prueba',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Prueba',
            'fecha_inicio' => '2026-01-01',
        ]);

        $movimiento = MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => 'gasto',
            'fecha' => '2026-06-15',
            'concepto' => 'Material de piscina',
            'importe' => 150,
        ]);

        Livewire::actingAs($empleado)
            ->test(EconomicMovementsManager::class)
            ->call('eliminar', $movimiento->id)
            ->call('ejecutarEliminar')
            ->assertForbidden();

        $this->assertDatabaseHas('movimientos_economicos', [
            'id' => $movimiento->id,
            'concepto' => 'Material de piscina',
        ]);
    }

    public function test_empleado_no_puede_acceder_a_movimientos_economicos(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rol->id);

        $this->actingAs($empleado)
            ->get(route('economia.movimientos'))
            ->assertForbidden();
    }

    public function test_contabilidad_puede_acceder_a_movimientos_economicos(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::ACCOUNTANT],
            ['display_name' => 'Contabilidad']
        );

        $contable = User::factory()->create();
        $contable->roles()->attach($rol->id);

        $this->actingAs($contable)
            ->get(route('economia.movimientos'))
            ->assertOk();
    }
}
