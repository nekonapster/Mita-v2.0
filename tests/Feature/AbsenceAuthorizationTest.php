<?php

namespace Tests\Feature;

use App\Livewire\Rrhh\AbsenceIndex;
use App\Models\Ausencia;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AbsenceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_no_puede_aprobar_ausencias(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $empleado->id,
            'tipo' => 'vacaciones',
            'fecha_hora_inicio' => '2026-06-15 09:00:00',
            'fecha_hora_fin' => '2026-06-20 18:00:00',
            'estado' => 'pendiente',
        ]);

        Livewire::actingAs($usuario)
            ->test(AbsenceIndex::class)
            ->call('aprobar', $ausencia->id)
            ->assertForbidden();

        $this->assertDatabaseHas('ausencias', [
            'id' => $ausencia->id,
            'estado' => 'pendiente',
            'aprobado_por' => null,
        ]);
    }
    public function test_empleado_no_puede_rechazar_ausencias(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $empleado->id,
            'tipo' => 'vacaciones',
            'fecha_hora_inicio' => '2026-06-15 09:00:00',
            'fecha_hora_fin' => '2026-06-20 18:00:00',
            'estado' => 'pendiente',
        ]);

        Livewire::actingAs($usuario)
            ->test(AbsenceIndex::class)
            ->call('rechazar', $ausencia->id)
            ->assertForbidden();

        // La solicitud debe continuar pendiente.
        $this->assertDatabaseHas('ausencias', [
            'id' => $ausencia->id,
            'estado' => 'pendiente',
            'aprobado_por' => null,
        ]);
    }
    public function test_empleado_no_puede_registrar_ausencias_desde_rrhh(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(AbsenceIndex::class)
            ->set('empleado_id', $empleado->id)
            ->set('tipo', 'vacaciones')
            ->set('fecha_hora_inicio', '2026-06-15 09:00')
            ->set('fecha_hora_fin', '2026-06-20 18:00')
            ->set('observaciones', 'Ausencia no autorizada')
            ->call('registrarAusencia')
            ->assertForbidden();

        // No debe haberse creado ninguna ausencia.
        $this->assertDatabaseCount('ausencias', 0);
    }
    public function test_empleado_no_puede_eliminar_ausencias(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $empleado->id,
            'tipo' => 'vacaciones',
            'fecha_hora_inicio' => '2026-06-15 09:00:00',
            'fecha_hora_fin' => '2026-06-20 18:00:00',
            'estado' => 'aprobada',
        ]);

        Livewire::actingAs($usuario)
            ->test(AbsenceIndex::class)
            ->call('eliminar', $ausencia->id)
            ->assertForbidden();

        // La ausencia debe seguir existiendo.
        $this->assertDatabaseHas('ausencias', [
            'id' => $ausencia->id,
            'estado' => 'aprobada',
        ]);
    }
    public function test_empleado_no_puede_abrir_modal_crear_ausencia(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        Livewire::actingAs($usuario)
            ->test(AbsenceIndex::class)
            ->call('abrirModalCrear')
            ->assertForbidden();
    }
    public function test_rrhh_puede_registrar_ausencia(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::RRHH],
            ['display_name' => 'Recursos Humanos']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Pedro',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(AbsenceIndex::class)
            ->call('abrirModalCrear')
            ->assertSet('modalCrearOpen', true)
            ->set('empleado_id', $empleado->id)
            ->set('tipo', 'vacaciones')
            ->set('fecha_hora_inicio', '2026-06-15 09:00')
            ->set('fecha_hora_fin', '2026-06-20 18:00')
            ->call('registrarAusencia')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ausencias', [
            'empleado_id' => $empleado->id,
            'tipo' => 'vacaciones',
            'estado' => Ausencia::ESTADO_APROBADA,
            'aprobado_por' => $usuario->id,
        ]);
    }
}
