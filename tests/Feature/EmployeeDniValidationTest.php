<?php

namespace Tests\Feature;

use App\Livewire\Rrhh\EmployeeIndex;
use App\Models\Categoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\Empleado;

class EmployeeDniValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_dni_nie_superior_a_20_caracteres(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeIndex::class)
            ->set('nombre', 'Martín')
            ->set('apellidos', 'Prueba')
            ->set('dni_nie', '1234567890123456789012345')
            ->set('categoria_id', $categoria->id)
            ->call('guardar')
            ->assertHasErrors(['dni_nie' => 'max']);

        $this->assertDatabaseMissing('empleados', [
            'nombre' => 'Martín',
            'apellidos' => 'Prueba',
        ]);
    }
    public function test_permite_editar_empleado_conservando_su_dni_nie(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Martín',
            'apellidos' => 'Prueba',
            'dni_nie' => '12345678Z',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeIndex::class)
            ->call('abrirModalEditar', $empleado->id)
            ->set('nombre', 'Martín Actualizado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'id' => $empleado->id,
            'nombre' => 'Martín Actualizado',
            'dni_nie' => '12345678Z',
        ]);

        $this->assertDatabaseCount('empleados', 1);
    }
    public function test_no_permite_crear_empleados_con_dni_nie_duplicado(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'dni_nie' => '12345678Z',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EmployeeIndex::class)
            ->set('nombre', 'Andrés')
            ->set('apellidos', 'López')
            ->set('dni_nie', '12345678Z')
            ->set('categoria_id', $categoria->id)
            ->call('guardar')
            ->assertHasErrors(['dni_nie' => 'unique']);

        $this->assertDatabaseCount('empleados', 1);

        $this->assertDatabaseMissing('empleados', [
            'nombre' => 'Andrés',
            'apellidos' => 'López',
        ]);
    }
}
