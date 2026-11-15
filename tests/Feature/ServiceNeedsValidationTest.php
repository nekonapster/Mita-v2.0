<?php

namespace Tests\Feature;

use App\Livewire\Services\ServiceNeedsManager;
use App\Models\Actividad;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Instalacion;
use App\Models\NecesidadServicio;
use App\Models\Proyecto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\Asignacion;
use App\Models\Contrato;
use App\Models\Empleado;
use App\Models\Turno;

class ServiceNeedsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_necesidad_no_admite_instalacion_ajena_al_proyecto(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscinas de verano',
            'fecha_inicio' => '2027-06-01',
        ]);

        $instalacionA = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Cavaleri',
        ]);

        $instalacionB = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Francisco León',
        ]);

        // Solo Cavaleri está asociada al proyecto.
        $proyecto->instalaciones()->attach($instalacionA->id);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        Livewire::actingAs($admin)
            ->test(ServiceNeedsManager::class)
            ->set('proyecto_id', $proyecto->id)
            ->set('instalacion_id', $instalacionB->id)
            ->set('actividad_id', $actividad->id)
            ->set('categoria_id', $categoria->id)
            ->set('fecha_inicio_vigencia', '2027-06-01')
            ->set('fecha_fin_vigencia', '2027-06-30')
            ->set('dias_semana', [1, 2, 3, 4, 5])
            ->set('hora_inicio', '12:15')
            ->set('hora_fin', '20:45')
            ->set('plazas_necesarias', 1)
            ->call('guardar')
            ->assertHasErrors(['instalacion_id']);

        $this->assertDatabaseCount('necesidades_servicio', 0);
    }
    public function test_necesidad_admite_instalacion_asociada_al_proyecto(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Mairena',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscinas de verano',
            'fecha_inicio' => '2027-06-01',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Cavaleri',
        ]);

        // La instalación pertenece al proyecto.
        $proyecto->instalaciones()->attach($instalacion->id);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        Livewire::actingAs($admin)
            ->test(ServiceNeedsManager::class)
            ->set('proyecto_id', $proyecto->id)
            ->set('instalacion_id', $instalacion->id)
            ->set('actividad_id', $actividad->id)
            ->set('categoria_id', $categoria->id)
            ->set('fecha_inicio_vigencia', '2027-06-01')
            ->set('fecha_fin_vigencia', '2027-06-30')
            ->set('dias_semana', [1, 2, 3, 4, 5])
            ->set('hora_inicio', '12:15')
            ->set('hora_fin', '20:45')
            ->set('plazas_necesarias', 1)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('necesidades_servicio', [
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'plazas_necesarias' => 1,
        ]);
    }

    public function test_sincronizacion_muestra_error_si_reduccion_de_plazas_deja_empleados_sobrantes(): void
    {
        $admin = User::factory()->create();

        $rol = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Administrador']
        );

        $admin->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Cliente Test',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Proyecto Test',
            'fecha_inicio' => '2027-06-01',
        ]);

        $instalacion = Instalacion::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Test',
        ]);

        $proyecto->instalaciones()->attach($instalacion->id);

        $actividad = Actividad::create([
            'nombre' => 'Socorrismo',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Socorrista',
            'codigo' => 'SOC',
        ]);

        $necesidad = NecesidadServicio::create([
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha_inicio_vigencia' => '2027-06-01',
            'fecha_fin_vigencia' => '2027-06-30',
            'dias_semana' => [1, 2, 3, 4, 5, 6, 7],
            'hora_inicio' => '10:00:00',
            'hora_fin' => '18:00:00',
            'cruza_medianoche' => false,
            'plazas_necesarias' => 1,
        ]);

        $turno = Turno::create([
            'necesidad_servicio_id' => $necesidad->id,
            'proyecto_id' => $proyecto->id,
            'instalacion_id' => $instalacion->id,
            'actividad_id' => $actividad->id,
            'categoria_id' => $categoria->id,
            'fecha' => '2027-06-15',
            'hora_inicio' => '2027-06-15 10:00:00',
            'hora_fin' => '2027-06-15 18:00:00',
            'plazas_necesarias' => 2,
            'estado' => Turno::ESTADO_PUBLICADO,
        ]);

        foreach (['Juan', 'Pedro'] as $nombre) {
            $empleado = Empleado::create([
                'nombre' => $nombre,
                'apellidos' => 'Prueba',
                'categoria_id' => $categoria->id,
                'activo' => true,
            ]);

            $empleado->categoriasHabilitadas()->sync([$categoria->id]);

            Contrato::create([
                'empleado_id' => $empleado->id,
                'tipo_contrato' => 'Indefinido',
                'fecha_inicio' => '2027-01-01',
                'fecha_fin' => '2027-12-31',
                'jornada_semanal_horas' => 40,
            ]);

            Asignacion::create([
                'turno_id' => $turno->id,
                'empleado_id' => $empleado->id,
                'estado' => Asignacion::ESTADO_CONFIRMADO,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(ServiceNeedsManager::class)
            ->set('necesidadParaSyncId', $necesidad->id)
            ->set('modalSyncOpen', true)
            ->call('confirmarSync', true)
            ->assertSet('modalSyncOpen', true)
            ->assertSet('feedbackError', function ($mensaje) {
                return is_string($mensaje)
                    && str_contains($mensaje, '2 empleados asignados')
                    && str_contains($mensaje, '1 plazas');
            });

        // No debe cambiar la capacidad del turno.
        $this->assertSame(2, $turno->fresh()->plazas_necesarias);

        // Las dos asignaciones deben permanecer intactas.
        $this->assertSame(2, $turno->asignacionesActivas()->count());
    }
}
