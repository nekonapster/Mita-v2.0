<?php

namespace Tests\Feature;

use App\Livewire\Economics\EconomicsDashboard;
use App\Models\CierreEconomico;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Services\EconomicClosingService;

class EconomicsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_economico_utiliza_importes_consolidados(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Julio tiene un cierre económico oficial.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => [],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        $this->actingAs($usuario);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('fechaDesde', '2026-07-01')
            ->set('fechaHasta', '2026-07-31')
            ->assertSet('proyectoSeleccionado', $proyecto->id)
            ->assertViewHas('informe', function ($informe) {
                return $informe !== null
                    && ($informe['tipo_informe'] ?? null) === 'consolidado'
                    && $informe['coste_personal_real'] == 120
                    && $informe['ingresos_totales'] == 200
                    && $informe['resultado_estimado'] == 50;
            })
            ->assertSee('Informe consolidado');
    }

    public function test_panel_advierte_consultas_parciales_de_meses_cerrados(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => [],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        $this->actingAs($usuario);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('fechaDesde', '2026-07-10')
            ->set('fechaHasta', '2026-07-20')
            ->assertViewHas('informe', function ($informe) {
                return ($informe['tipo_informe'] ?? null) === 'dinamico'
                    && ($informe['advertencia_cierre'] ?? false) === true;
            })
            ->assertSee('Informe dinámico')
            ->assertSee('Advertencia sobre el cierre económico');
    }

    public function test_panel_muestra_informe_mixto(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Julio cerrado.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 0,
                'horas_realizadas' => 0,
                'coste_personal_previsto' => 0,
                'coste_personal_real' => 0,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 0,
                'resultado_estimado' => 200,
                'desglose_explicativo' => [],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        // Agosto abierto.
        \App\Models\MovimientoEconomico::create([
            'proyecto_id' => $proyecto->id,
            'tipo' => \App\Models\MovimientoEconomico::TIPO_INGRESO,
            'fecha' => '2026-08-10',
            'concepto' => 'Factura agosto',
            'importe' => 500,
        ]);

        $this->actingAs($usuario);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('fechaDesde', '2026-07-01')
            ->set('fechaHasta', '2026-08-31')
            ->assertViewHas('informe', function ($informe) {
                return ($informe['tipo_informe'] ?? null) === 'mixto'
                    && $informe['ingresos_totales'] == 700;
            })
            ->assertSee('Informe mixto')
            ->assertSee('700,00');
    }

    public function test_panel_advierte_consulta_por_instalacion_de_mes_cerrado(): void
    {
        $usuario = User::factory()->create();

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // Existe un cierre oficial de julio.
        CierreEconomico::create([
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'datos_consolidados' => [
                'horas_previstas' => 8,
                'horas_realizadas' => 8,
                'coste_personal_previsto' => 120,
                'coste_personal_real' => 120,
                'coste_previsto_incompleto' => false,
                'coste_real_incompleto' => false,
                'ingresos_totales' => 200,
                'gastos_adicionales' => 30,
                'resultado_estimado' => 50,
                'desglose_explicativo' => [],
            ],
            'cerrado_por' => $usuario->id,
            'cerrado_en' => now(),
        ]);

        $this->actingAs($usuario);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('fechaDesde', '2026-07-01')
            ->set('fechaHasta', '2026-07-31')
            ->set('instalacionSeleccionada', 1)
            ->assertViewHas('informe', function ($informe) {
                return ($informe['tipo_informe'] ?? null) === 'dinamico'
                    && ($informe['advertencia_cierre'] ?? false) === true;
            })
            ->assertSee('Informe dinámico')
            ->assertSee('Advertencia sobre el cierre económico');
    }

    public function test_empleado_no_puede_ejecutar_cierre_desde_livewire(): void
    {
        $usuario = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::EMPLOYEE,
            'display_name' => 'Empleado',
        ]);

        $usuario->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($usuario);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('ejecutarCierre')
            ->assertForbidden();

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }

    public function test_administrador_no_puede_cerrar_sin_confirmacion(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('ejecutarCierre');

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }
    public function test_administrador_puede_preparar_confirmacion_de_cierre(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true);

        // Preparar la confirmación no debe guardar nada.
        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }

    public function test_empleado_no_puede_preparar_cierre_economico(): void
    {
        $empleado = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::EMPLOYEE,
            'display_name' => 'Empleado',
        ]);

        $empleado->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($empleado);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertForbidden()
            ->assertSet('modalCierreOpen', false);

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }
    public function test_administrador_ejecuta_cierre_confirmado(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true)
            ->call('ejecutarCierre')
            ->assertSet('modalCierreOpen', false);

        $this->assertDatabaseHas('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
            'cerrado_por' => $administrador->id,
        ]);
    }
    public function test_no_permite_cambiar_periodo_despues_de_preparar_cierre(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true)

            // Intentar cambiar el período después de prepararlo.
            ->set('mesCierre', 8)
            ->call('ejecutarCierre')
            ->assertForbidden();

        // Julio no debe cerrarse.
        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);

        // Agosto tampoco.
        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 8,
        ]);
    }
    public function test_no_permite_cambiar_proyecto_despues_de_preparar_cierre(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyectoA = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina A',
            'fecha_inicio' => '2026-01-01',
        ]);

        $proyectoB = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina B',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyectoA->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true)
            ->set('proyectoSeleccionado', $proyectoB->id)
            ->call('ejecutarCierre')
            ->assertForbidden();

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyectoA->id,
            'anio' => 2026,
            'mes' => 7,
        ]);

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyectoB->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }
    public function test_no_permite_reutilizar_confirmacion_de_cierre(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->call('ejecutarCierre')
            ->assertSet('modalCierreOpen', false)

            // Intentar ejecutar nuevamente sin preparar otro cierre.
            ->call('ejecutarCierre')
            ->assertForbidden();

        $this->assertSame(
            1,
            CierreEconomico::where('proyecto_id', $proyecto->id)
                ->where('anio', 2026)
                ->where('mes', 7)
                ->count()
        );
    }

    public function test_cancelar_confirmacion_impide_ejecutar_cierre(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true)

            // El administrador cancela la operación.
            ->call('cancelarCierre')
            ->assertSet('modalCierreOpen', false)

            // Intenta ejecutar el cierre después de cancelarlo.
            ->call('ejecutarCierre')
            ->assertForbidden();

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }
    public function test_empleado_no_ve_controles_de_cierre_economico(): void
    {
        $empleado = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::EMPLOYEE,
            'display_name' => 'Empleado',
        ]);

        $empleado->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($empleado);

        Livewire::test(EconomicsDashboard::class)
            ->assertDontSee('Preparar cierre económico');
    }
    public function test_administrador_ve_controles_de_cierre_economico(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->assertSee('Preparar cierre económico');
    }

    public function test_preparar_cierre_muestra_modal_de_confirmacion(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true)
            ->assertSee('Confirmar cierre económico')
            ->assertSee('Piscina Municipal')
            ->assertSee('julio')
            ->assertSee('2026')
            ->assertSee('Cancelar cierre');
    }
    public function test_panel_muestra_error_al_cerrar_periodo_futuro(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2027)
            ->set('mesCierre', 12)
            ->call('prepararCierre')
            ->call('ejecutarCierre')
            ->assertHasErrors(['periodo'])
            ->assertSet('modalCierreOpen', true)
            ->assertSee('No se pudo realizar el cierre económico');
        // ->call('ejecutarCierre')
        // ->assertHasErrors(['periodo'])
        // ->assertSet('modalCierreOpen', true)
        // ->assertSee('Confirmar cierre económico')
        // ->dump();

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2027,
            'mes' => 12,
        ]);
    }

    public function test_dos_componentes_pueden_preparar_cierres_independientes(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyectoA = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina A',
            'fecha_inicio' => '2026-01-01',
        ]);

        $proyectoB = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina B',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        // Primera pestaña.
        $pestanaA = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyectoA->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre');

        // Segunda pestaña, compartiendo la misma sesión.
        $pestanaB = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyectoB->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre');

        // Cada pestaña debe conservar su propia confirmación.
        $pestanaA
            ->assertSet('modalCierreOpen', true)
            ->call('ejecutarCierre')
            ->assertHasNoErrors();

        $pestanaB
            ->assertSet('modalCierreOpen', true)
            ->call('ejecutarCierre')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cierre_economicos', [
            'proyecto_id' => $proyectoA->id,
            'anio' => 2026,
            'mes' => 7,
        ]);

        $this->assertDatabaseHas('cierre_economicos', [
            'proyecto_id' => $proyectoB->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }
    public function test_no_permite_ejecutar_cierre_con_token_caducado(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        $componente = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre')
            ->assertSet('modalCierreOpen', true);

        // Simular que han transcurrido más de diez minutos.
        $this->travel(11)->minutes();

        $componente
            ->call('ejecutarCierre')
            ->assertForbidden();

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }

    public function test_no_permite_utilizar_token_de_otro_usuario(): void
    {
        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administradorA = User::factory()->create();
        $administradorB = User::factory()->create();

        $administradorA->roles()->attach($rol->id);
        $administradorB->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        // El administrador A prepara el cierre.
        $this->actingAs($administradorA);

        $componente = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre');

        $token = $componente->get('tokenCierre');

        // El administrador B intenta utilizar ese mismo token.
        $this->actingAs($administradorB);

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->set('tokenCierre', $token)
            ->call('ejecutarCierre')
            ->assertForbidden();

        $this->assertDatabaseMissing('cierre_economicos', [
            'proyecto_id' => $proyecto->id,
            'anio' => 2026,
            'mes' => 7,
        ]);
    }

    public function test_no_permite_ejecutar_cierre_si_token_esta_bloqueado(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        $componente = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->set('anioCierre', 2026)
            ->set('mesCierre', 7)
            ->call('prepararCierre');

        $token = $componente->get('tokenCierre');

        // Simular que otra solicitud está utilizando este token.
        $bloqueo = \Illuminate\Support\Facades\Cache::lock(
            'mita.cierre.lock.' . $token,
            30
        );

        $this->assertTrue($bloqueo->get());

        try {
            $componente
                ->call('ejecutarCierre')
                ->assertForbidden();

            $this->assertDatabaseMissing('cierre_economicos', [
                'proyecto_id' => $proyecto->id,
                'anio' => 2026,
                'mes' => 7,
            ]);
        } finally {
            $bloqueo->release();
        }
    }
    public function test_panel_muestra_historial_de_cierres_economicos(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        // Crear un cierre real mediante el servicio existente.
        app(EconomicClosingService::class)->cerrarPeriodo(
            $proyecto,
            2026,
            7,
            $administrador
        );

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->assertSee('Historial de cierres económicos')
            ->assertSee('julio')
            ->assertSee('2026');
    }

    public function test_historial_no_muestra_cierres_de_otros_proyectos(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyectoA = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina A',
            'fecha_inicio' => '2026-01-01',
        ]);

        $proyectoB = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina B',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        // Cierre de julio para el proyecto A.
        app(EconomicClosingService::class)->cerrarPeriodo(
            $proyectoA,
            2026,
            7,
            $administrador
        );

        // Cierre de agosto para el proyecto B.
        app(EconomicClosingService::class)->cerrarPeriodo(
            $proyectoB,
            2026,
            8,
            $administrador
        );

        $componente = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyectoA->id)
            ->assertSee('Historial de cierres económicos');

        $historial = $componente->viewData('historialCierres');

        $this->assertCount(1, $historial);

        $this->assertEquals(
            $proyectoA->id,
            $historial->first()->proyecto_id
        );

        $this->assertEquals(7, $historial->first()->mes);

        $this->assertEquals(2026, $historial->first()->anio);
    }
    public function test_historial_muestra_usuario_responsable_del_cierre(): void
    {
        $administrador = User::factory()->create([
            'name' => 'Martín Administrador',
        ]);

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        app(EconomicClosingService::class)->cerrarPeriodo(
            $proyecto,
            2026,
            7,
            $administrador
        );

        $cierre = CierreEconomico::where('proyecto_id', $proyecto->id)
            ->where('anio', 2026)
            ->where('mes', 7)
            ->firstOrFail();

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->assertSee('Historial de cierres económicos')
            ->assertSee('Martín Administrador')
            ->assertSee('Fecha del cierre')
            ->assertSee($cierre->cerrado_en->format('d/m/Y H:i'));
    }
    public function test_historial_muestra_importes_economicos_consolidados(): void
    {
        $administrador = User::factory()->create();

        $rol = \App\Models\Role::create([
            'name' => \App\Models\Role::ADMIN,
            'display_name' => 'Administrador',
        ]);

        $administrador->roles()->attach($rol->id);

        $cliente = Cliente::create([
            'razon_social' => 'Ayuntamiento de Pruebas',
        ]);

        $proyecto = Proyecto::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Piscina Municipal',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($administrador);

        app(EconomicClosingService::class)->cerrarPeriodo(
            $proyecto,
            2026,
            7,
            $administrador
        );
        $cierre = CierreEconomico::where('proyecto_id', $proyecto->id)
            ->where('anio', 2026)
            ->where('mes', 7)
            ->firstOrFail();

        $datos = $cierre->datos_consolidados;

        $this->assertIsArray($datos);

        foreach (
            [
                'ingresos_totales',
                'coste_personal_real',
                'gastos_adicionales',
                'resultado_estimado',
            ] as $campo
        ) {
            $this->assertArrayHasKey($campo, $datos);
        }

        $componente = Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id);

        foreach (
            [
                'ingresos_totales',
                'coste_personal_real',
                'gastos_adicionales',
                'resultado_estimado',
            ] as $campo
        ) {
            $importe = number_format((float) $datos[$campo], 2, ',', '.') . ' €';

            $componente->assertSee($importe);
        }

        Livewire::test(EconomicsDashboard::class)
            ->set('proyectoSeleccionado', $proyecto->id)
            ->assertSee('Historial de cierres económicos')
            ->assertSee('Ingresos consolidados')
            ->assertSee('Coste personal consolidado')
            ->assertSee('Gastos consolidados')
            ->assertSee('Resultado consolidado');
    }
}
