<?php

namespace Tests\Feature;

use App\Livewire\Coordinacion\AusenciasSustituciones;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;


class CoordinatorSubstitutionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_agregar_y_eliminar_tramos_de_sustitucion(): void
    {
        Livewire::test(AusenciasSustituciones::class)
            ->call('agregarSustituto')
            ->call('agregarSustituto')
            ->assertCount('sustitutos', 2)
            ->call('eliminarSustituto', 1)
            ->assertCount('sustitutos', 1)
            ->assertSet('sustitutos.0.id', 2);
    }

    public function test_empleado_normal_no_puede_acceder_al_formulario_de_sustituciones(): void
    {
        $rolEmpleado = Role::firstOrCreate(
            ['name' => 'employee'],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();

        $empleado->roles()->attach($rolEmpleado->id);

        $this->actingAs($empleado)
            ->get(route('coordinacion.sustituciones'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_acceder_al_formulario_de_sustituciones(): void
    {
        $rolCoordinador = Role::firstOrCreate(
            ['name' => 'coordinator'],
            ['display_name' => 'Coordinador']
        );

        $coordinador = User::factory()->create();

        $coordinador->roles()->attach($rolCoordinador->id);

        $this->actingAs($coordinador)
            ->get(route('coordinacion.sustituciones'))
            ->assertOk();
    }

    public function test_empleado_normal_no_puede_guardar_sustituciones(): void
    {
        $rolEmpleado = Role::firstOrCreate(
            ['name' => 'employee'],
            ['display_name' => 'Empleado']
        );

        $empleado = User::factory()->create();
        $empleado->roles()->attach($rolEmpleado->id);

        Livewire::actingAs($empleado)
            ->test(AusenciasSustituciones::class)
            ->call('guardar')
            ->assertForbidden();

        $this->assertDatabaseCount('ausencias', 0);
        $this->assertDatabaseCount('sustituciones', 0);
        $this->assertDatabaseCount('sustitucion_tramos', 0);
    }
}
