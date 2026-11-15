<?php

namespace Tests\Feature;

use App\Livewire\Rrhh\IncidentIndex;
use App\Models\Incidencia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_no_puede_eliminar_incidencias(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rol->id);

        $incidencia = Incidencia::create([
            'titulo' => 'Incidencia de prueba',
            'descripcion' => 'Prueba de autorización',
            'fecha' => '2026-11-15',
            'estado' => 'abierta',
            'user_id' => $empleado->id,
        ]);

        Livewire::actingAs($empleado)
            ->test(IncidentIndex::class)
            ->call('eliminar', $incidencia->id)
            ->assertForbidden();

        $this->assertDatabaseHas('incidencias', [
            'id' => $incidencia->id,
        ]);
    }

    public function test_empleado_no_puede_cambiar_estado_de_incidencia_ajena(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::EMPLOYEE],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rol->id);

        $creador = User::factory()->create();

        $incidencia = Incidencia::create([
            'titulo' => 'Incidencia de prueba',
            'descripcion' => 'Incidencia creada por otro usuario',
            'fecha' => '2026-11-15',
            'estado' => 'abierta',
            'user_id' => $creador->id,
        ]);

        Livewire::actingAs($empleado)
            ->test(IncidentIndex::class)
            ->call('cambiarEstado', $incidencia->id, 'resuelta')
            ->assertForbidden();

        $this->assertDatabaseHas('incidencias', [
            'id' => $incidencia->id,
            'estado' => 'abierta',
        ]);
    }
    public function test_usuario_sin_rol_no_puede_acceder_a_incidencias(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('rrhh.incidencias'))
            ->assertForbidden();
    }

    public function test_rrhh_puede_acceder_a_incidencias(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::RRHH],
            ['display_name' => 'Recursos Humanos']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $this->actingAs($usuario)
            ->get(route('rrhh.incidencias'))
            ->assertOk();
    }
}
