<?php

namespace Tests\Feature;

use App\Livewire\Economics\TimeEntryValidation;
use App\Models\Actividad;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Instalacion;
use App\Models\Proyecto;
use App\Models\RegistroHora;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimeEntryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_no_puede_validar_horas(): void
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

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Prueba',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
        ]);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Prueba',
            'fecha_inicio' => '2026-01-01',
        ]);

        $turno = Turno::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2026-06-15',
            'hora_inicio' => '2026-06-15 08:00:00',
            'hora_fin' => '2026-06-15 12:00:00',
            'plazas_necesarias' => 1,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        $asignacion = Asignacion::create([
            'turno_id' => $turno->id,
            'empleado_id' => $empleado->id,
            'estado' => Asignacion::ESTADO_CONFIRMADO,
        ]);

        $registro = RegistroHora::create([
            'asignacion_id' => $asignacion->id,
            'inicio_real' => '2026-06-15 08:00:00',
            'fin_real' => '2026-06-15 12:00:00',
            'descanso_minutos' => 0,
            'horas_computables' => 4,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
        ]);

        // Un empleado intenta validar horas directamente mediante Livewire.
        Livewire::actingAs($usuario)
            ->test(TimeEntryValidation::class)
            ->call('validar', $registro->id)
            ->assertForbidden();

        // El registro debe permanecer sin validar.
        $this->assertDatabaseHas('registros_horas', [
            'id' => $registro->id,
            'estado' => RegistroHora::ESTADO_PENDIENTE,
            'validado_por' => null,
        ]);
    }
    public function test_empleado_no_puede_editar_horas_desde_administracion(): void
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

    $cliente = Cliente::create([
        'razon_social' => 'Cliente Prueba',
    ]);

    $instalacion = Instalacion::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Piscina Municipal',
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Socorrismo',
    ]);

    $proyecto = Proyecto::create([
        'cliente_id' => $cliente->id,
        'nombre' => 'Proyecto Prueba',
        'fecha_inicio' => '2026-01-01',
    ]);

    $turno = Turno::create([
        'proyecto_id' => $proyecto->id,
        'instalacion_id' => $instalacion->id,
        'actividad_id' => $actividad->id,
        'categoria_id' => $categoria->id,
        'fecha' => '2026-06-15',
        'hora_inicio' => '2026-06-15 08:00:00',
        'hora_fin' => '2026-06-15 12:00:00',
        'plazas_necesarias' => 1,
        'estado' => Turno::ESTADO_PUBLICADO,
    ]);

    $asignacion = Asignacion::create([
        'turno_id' => $turno->id,
        'empleado_id' => $empleado->id,
        'estado' => Asignacion::ESTADO_CONFIRMADO,
    ]);

    $registro = RegistroHora::create([
        'asignacion_id' => $asignacion->id,
        'inicio_real' => '2026-06-15 08:00:00',
        'fin_real' => '2026-06-15 12:00:00',
        'descanso_minutos' => 0,
        'horas_computables' => 4,
        'estado' => RegistroHora::ESTADO_PENDIENTE,
    ]);

    Livewire::actingAs($usuario)
        ->test(TimeEntryValidation::class)
        ->set('registroId', $registro->id)
        ->set('editInicioReal', '2026-06-15 08:00')
        ->set('editFinReal', '2026-06-15 18:00')
        ->set('editDescansoMinutos', 0)
        ->set('editObservaciones', 'Modificación no autorizada')
        ->call('guardarEdicion')
        ->assertForbidden();

    // Verificar que las horas originales no cambiaron.
    $registro->refresh();

    $this->assertEquals(4, $registro->horas_computables);
    $this->assertNull($registro->modificado_por);
}
public function test_empleado_no_puede_abrir_modal_administrativo_de_horas(): void
{
    $rol = Role::firstOrCreate(
        ['name' => Role::EMPLOYEE],
        ['display_name' => 'Empleado']
    );

    $usuario = User::factory()->create();
    $usuario->roles()->attach($rol->id);

    Livewire::actingAs($usuario)
        ->test(TimeEntryValidation::class)
        ->call('abrirModalEditar', 1)
        ->assertForbidden();
}
public function test_administrador_puede_abrir_modal_administrativo_de_horas(): void
{
    $rol = Role::firstOrCreate(
        ['name' => Role::ADMIN],
        ['display_name' => 'Administrador']
    );

    $usuario = User::factory()->create();
    $usuario->roles()->attach($rol->id);

    Livewire::actingAs($usuario)
        ->test(TimeEntryValidation::class)
        ->call('abrirModalEditar', 999999)
        ->assertNotFound();
}

}