<?php

namespace Tests\Feature;

use App\Livewire\Coordinacion\AusenciasSustituciones;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use App\Models\Ausencia;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Sustitucion;
use App\Models\SustitucionTramo;
use Carbon\Carbon;


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

    public function test_coordinador_puede_ver_historial_de_sustituciones(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => 'coordinator'],
            ['display_name' => 'Coordinador']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON',
        ]);

        $ausente = Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'activo' => true,
            'categoria_id' => $categoria->id,
        ]);

        $sustituto = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín',
            'activo' => true,
            'categoria_id' => $categoria->id,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $ausente->id,
            'tipo' => Ausencia::TIPO_PERMISO,
            'fecha_hora_inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid')->utc(),
            'fecha_hora_fin' => Carbon::parse('2026-11-25 13:00', 'Europe/Madrid')->utc(),
            'estado' => Ausencia::ESTADO_PENDIENTE,
        ]);

        $sustitucion = Sustitucion::create([
            'ausencia_id' => $ausencia->id,
            'modalidad_pago' => Sustitucion::PAGO_EMPRESA,
        ]);

        SustitucionTramo::create([
            'sustitucion_id' => $sustitucion->id,
            'empleado_id' => $sustituto->id,
            'inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid')->utc(),
            'fin' => Carbon::parse('2026-11-25 11:00', 'Europe/Madrid')->utc(),
        ]);

        Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->assertSee('Historial de sustituciones')
            ->assertSee('Laura Ruiz')
            ->assertSee('David Martín')
            ->assertSee('ATAAA');
    }

    public function test_historial_permite_consultar_sustituciones_de_la_segunda_pagina(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => 'coordinator'],
            ['display_name' => 'Coordinador']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON',
        ]);

        $empleado = \App\Models\Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'activo' => true,
            'categoria_id' => $categoria->id,
        ]);

        $idSustitucionAntigua = null;

        for ($i = 1; $i <= 11; $i++) {
            $ausencia = \App\Models\Ausencia::create([
                'empleado_id' => $empleado->id,
                'tipo' => 'permiso',
                'fecha_hora_inicio' => \Carbon\Carbon::parse('2026-11-25 09:00', 'Europe/Madrid')->utc(),
                'fecha_hora_fin' => \Carbon\Carbon::parse('2026-11-25 13:00', 'Europe/Madrid')->utc(),
                'estado' => 'pendiente',
                'observaciones' => "Registro historial {$i}",
            ]);

            $sustitucion = \App\Models\Sustitucion::create([
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => 'empresa',
            ]);

            if ($i === 1) {
                $idSustitucionAntigua = $sustitucion->id;
            }
        }

        $this->actingAs($usuario)
            ->get(route('coordinacion.sustituciones'))
            ->assertOk()
            ->assertDontSee('wire:key="historial-' . $idSustitucionAntigua . '"', false);

        $this->get(route('coordinacion.sustituciones', ['page' => 2]))
            ->assertOk()
            ->assertSee('wire:key="historial-' . $idSustitucionAntigua . '"', false);
    }

    public function test_historial_filtra_por_empleado_ausente(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => 'coordinator'],
            ['display_name' => 'Coordinador']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Monitor',
            'codigo' => 'MON',
        ]);

        $laura = \App\Models\Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ana = \App\Models\Empleado::create([
            'nombre' => 'Ana',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        foreach ([$laura, $ana] as $empleado) {
            $ausencia = \App\Models\Ausencia::create([
                'empleado_id' => $empleado->id,
                'tipo' => 'permiso',
                'fecha_hora_inicio' => '2026-11-25 08:00:00',
                'fecha_hora_fin' => '2026-11-25 12:00:00',
                'estado' => 'pendiente',
            ]);

            $sustitucion = \App\Models\Sustitucion::create([
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => 'empresa',
            ]);

            $ids[$empleado->id] = $sustitucion->id;
        }

        Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->assertSee('wire:key="historial-' . $ids[$laura->id] . '"', false)
            ->assertSee('wire:key="historial-' . $ids[$ana->id] . '"', false)
            ->set('empleadoHistorialFiltro', $laura->id)
            ->assertSee('wire:key="historial-' . $ids[$laura->id] . '"', false)
            ->assertDontSee('wire:key="historial-' . $ids[$ana->id] . '"', false);
    }

    public function test_cambiar_filtro_del_historial_reinicia_la_paginacion(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => 'coordinator'],
            ['display_name' => 'Coordinador']
        );

        $usuario = User::factory()->create();
        $usuario->roles()->attach($rol->id);

        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Monitor',
            'codigo' => 'MON',
        ]);

        $laura = \App\Models\Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ana = \App\Models\Empleado::create([
            'nombre' => 'Ana',
            'apellidos' => 'García',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        for ($i = 0; $i < 11; $i++) {
            $ausencia = \App\Models\Ausencia::create([
                'empleado_id' => $laura->id,
                'tipo' => 'permiso',
                'fecha_hora_inicio' => '2026-11-25 08:00:00',
                'fecha_hora_fin' => '2026-11-25 12:00:00',
                'estado' => 'pendiente',
            ]);

            \App\Models\Sustitucion::create([
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => 'empresa',
            ]);
        }

        $ausenciaAna = \App\Models\Ausencia::create([
            'empleado_id' => $ana->id,
            'tipo' => 'permiso',
            'fecha_hora_inicio' => '2026-11-25 08:00:00',
            'fecha_hora_fin' => '2026-11-25 12:00:00',
            'estado' => 'pendiente',
        ]);

        $sustitucionAna = \App\Models\Sustitucion::create([
            'ausencia_id' => $ausenciaAna->id,
            'modalidad_pago' => 'empresa',
        ]);

        Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->call('setPage', 2)
            ->assertDontSee(
                'wire:key="historial-' . $sustitucionAna->id . '"',
                false
            )
            ->set('empleadoHistorialFiltro', $ana->id)
            ->assertSee(
                'wire:key="historial-' . $sustitucionAna->id . '"',
                false
            )
            ->assertSet('paginators.page', 1);
    }

    public function test_planificador_puede_consultar_sustituciones(): void
    {
        $rol = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $planificador = User::factory()->create();
        $planificador->roles()->attach($rol->id);

        $this->actingAs($planificador)
            ->get(route('coordinacion.sustituciones'))
            ->assertOk();
    }

    public function test_planificador_solo_ve_historial_y_coordinador_puede_registrar(): void
    {
        $rolPlanner = Role::firstOrCreate(
            ['name' => Role::PLANNER],
            ['display_name' => 'Planificador']
        );

        $rolCoordinador = Role::firstOrCreate(
            ['name' => 'coordinator'],
            ['display_name' => 'Coordinador']
        );

        $planificador = User::factory()->create();
        $planificador->roles()->attach($rolPlanner->id);

        $coordinador = User::factory()->create();
        $coordinador->roles()->attach($rolCoordinador->id);

        Livewire::actingAs($planificador)
            ->test(AusenciasSustituciones::class)
            ->assertSee('Historial de sustituciones')
            ->assertDontSee('Registrar ausencia de empleado')
            ->assertDontSee('wire:click="guardar"', false);

        Livewire::actingAs($coordinador)
            ->test(AusenciasSustituciones::class)
            ->assertSee('Historial de sustituciones')
            ->assertSee('Registrar ausencia de empleado')
            ->assertSee('wire:click="guardar"', false);
    }
}
