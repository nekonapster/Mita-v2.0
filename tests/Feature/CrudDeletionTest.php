<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserRoleManager;
use App\Livewire\Economics\CostRateManager;
use App\Livewire\Economics\EconomicMovementsManager;
use App\Livewire\Roster\WeeklyRoster;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\MovimientoEconomico;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\TarifaCoste;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrudDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'name' => Role::ADMIN,
            'display_name' => 'Administrador',
            'description' => 'Acceso total',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Tester',
            'email' => 'admin_test@mita.es',
            'password' => bcrypt('password123'),
        ]);
        $this->admin->roles()->attach($roleAdmin->id);
    }

    public function test_delete_cost_rate(): void
    {
        $cat = Categoria::create(['nombre' => 'Socorrista', 'codigo' => 'SOC', 'activo' => true]);
        $tarifa = TarifaCoste::create([
            'categoria_id' => $cat->id,
            'coste_hora' => 20.00,
            'fecha_inicio' => now()->toDateString(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(CostRateManager::class)
            ->call('eliminar', $tarifa->id);

        $this->assertDatabaseMissing('tarifas_coste', ['id' => $tarifa->id]);
    }

    public function test_delete_economic_movement(): void
    {
        $cliente = Cliente::create(['razon_social' => 'Club Deportivo', 'activo' => true]);
        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => now()->toDateString(),
            'estado' => 'activo',
        ]);

        $mov = MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => 'gasto',
            'fecha' => now()->toDateString(),
            'concepto' => 'Material primeros auxilios',
            'importe' => 150.00,
        ]);

        Livewire::actingAs($this->admin)
            ->test(EconomicMovementsManager::class)
            ->call('eliminar', $mov->id);

        $this->assertDatabaseMissing('movimientos_economicos', ['id' => $mov->id]);
    }

    public function test_delete_user_in_user_role_manager(): void
    {
        $user = User::create([
            'name' => 'Usuario Borrable',
            'email' => 'borrame@mita.es',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($this->admin)
            ->test(UserRoleManager::class)
            ->call('confirmarEliminar', $user->id)
            ->call('eliminar');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
