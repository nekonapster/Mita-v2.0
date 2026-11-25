<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\Empleado;
use App\Models\Sustitucion;
use App\Models\SustitucionTramo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Categoria;
use Livewire\Livewire;
use App\Livewire\Coordinacion\AusenciasSustituciones;
use App\Models\Role;
use App\Models\User;

class SubstitutionTest extends TestCase
{

    use RefreshDatabase;

    public function test_una_ausencia_puede_tener_varios_tramos_de_sustitucion(): void
    {

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

        $sustituto1 = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín',
            'activo' => true,
            'categoria_id' => $categoria->id,
        ]);

        $sustituto2 = Empleado::create([
            'nombre' => 'Elena',
            'apellidos' => 'Navarro',
            'activo' => true,
            'categoria_id' => $categoria->id,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $ausente->id,
            'tipo' => Ausencia::TIPO_BAJA,
            'fecha_hora_inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-11-25 17:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        $sustitucion = Sustitucion::create([
            'ausencia_id' => $ausencia->id,
            'modalidad_pago' => 'empresa',
        ]);

        SustitucionTramo::create([
            'sustitucion_id' => $sustitucion->id,
            'empleado_id' => $sustituto1->id,
            'inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid'),
            'fin' => Carbon::parse('2026-11-25 11:00', 'Europe/Madrid'),
        ]);

        SustitucionTramo::create([
            'sustitucion_id' => $sustitucion->id,
            'empleado_id' => $sustituto2->id,
            'inicio' => Carbon::parse('2026-11-25 11:00', 'Europe/Madrid'),
            'fin' => Carbon::parse('2026-11-25 15:00', 'Europe/Madrid'),
        ]);

        $this->assertCount(2, $sustitucion->tramos);

        $this->assertEqualsCanonicalizing(
            [$sustituto1->id, $sustituto2->id],
            $sustitucion->tramos->pluck('empleado_id')->all()
        );

        $this->assertDatabaseCount('asignaciones', 0);
        $this->assertDatabaseCount('registros_horas', 0);
    }

    public function test_no_permite_tramos_con_fin_anterior_o_igual_al_inicio(): void
    {
        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $empleado->id,
            'tipo' => Ausencia::TIPO_PERMISO,
            'fecha_hora_inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-11-25 17:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        $sustitucion = Sustitucion::create([
            'ausencia_id' => $ausencia->id,
            'modalidad_pago' => Sustitucion::PAGO_EMPRESA,
        ]);

        foreach (
            [
                ['09:00', '09:00'],
                ['11:00', '09:00'],
            ] as [$inicio, $fin]
        ) {
            try {
                SustitucionTramo::create([
                    'sustitucion_id' => $sustitucion->id,
                    'empleado_id' => $empleado->id,
                    'inicio' => Carbon::parse("2026-11-25 {$inicio}", 'Europe/Madrid'),
                    'fin' => Carbon::parse("2026-11-25 {$fin}", 'Europe/Madrid'),
                ]);

                $this->fail('Se permitió guardar un tramo con horario inválido.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('fin', $e->errors());
            }
        }

        $this->assertDatabaseCount('sustitucion_tramos', 0);
    }


    public function test_no_permite_tramos_fuera_del_periodo_de_ausencia(): void
    {
        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON',
        ]);

        $ausente = Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $sustituto = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $ausente->id,
            'tipo' => Ausencia::TIPO_PERMISO,
            'fecha_hora_inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-11-25 17:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        $sustitucion = Sustitucion::create([
            'ausencia_id' => $ausencia->id,
            'modalidad_pago' => Sustitucion::PAGO_EMPRESA,
        ]);

        foreach (
            [
                ['08:00', '12:00'],
                ['15:00', '18:00'],
            ] as [$inicio, $fin]
        ) {
            try {
                SustitucionTramo::create([
                    'sustitucion_id' => $sustitucion->id,
                    'empleado_id' => $sustituto->id,
                    'inicio' => Carbon::parse("2026-11-25 {$inicio}", 'Europe/Madrid'),
                    'fin' => Carbon::parse("2026-11-25 {$fin}", 'Europe/Madrid'),
                ]);

                $this->fail('Se permitió guardar un tramo fuera de la ausencia.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('inicio', $e->errors());
            }
        }

        $this->assertDatabaseCount('sustitucion_tramos', 0);
    }


    public function test_no_permite_modalidades_de_pago_desconocidas(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $empleado->id,
            'tipo' => Ausencia::TIPO_PERMISO,
            'fecha_hora_inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-11-25 17:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        try {
            Sustitucion::create([
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => 'modalidad_inexistente',
            ]);

            $this->fail('Se permitió guardar una modalidad de pago desconocida.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('modalidad_pago', $e->errors());
        }

        $this->assertDatabaseCount('sustituciones', 0);
    }

    public function test_permite_las_tres_modalidades_de_pago(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Monitor de Natación',
            'codigo' => 'MON',
        ]);

        $empleado = Empleado::create([
            'nombre' => 'Laura',
            'apellidos' => 'Ruiz',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $ausencia = Ausencia::create([
            'empleado_id' => $empleado->id,
            'tipo' => Ausencia::TIPO_PERMISO,
            'fecha_hora_inicio' => Carbon::parse('2026-11-25 09:00', 'Europe/Madrid'),
            'fecha_hora_fin' => Carbon::parse('2026-11-25 17:00', 'Europe/Madrid'),
            'estado' => Ausencia::ESTADO_APROBADA,
        ]);

        $modalidades = [
            Sustitucion::PAGO_EMPRESA,
            Sustitucion::PAGO_EMPRESA_DESCUENTO_HORAS,
            Sustitucion::PAGO_MONITOR_SUSTITUIDO,
        ];

        foreach ($modalidades as $modalidad) {
            Sustitucion::create([
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => $modalidad,
            ]);
        }

        $this->assertDatabaseCount('sustituciones', 3);

        foreach ($modalidades as $modalidad) {
            $this->assertDatabaseHas('sustituciones', [
                'ausencia_id' => $ausencia->id,
                'modalidad_pago' => $modalidad,
            ]);
        }
    }

    public function test_no_duplica_horas_cuando_los_sustitutos_se_solapan(): void
    {
        Livewire::test(AusenciasSustituciones::class)
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('sustitutos', [
                [
                    'id' => 1,
                    'empleado_id' => '',
                    'inicio' => '2026-11-25T09:00',
                    'fin' => '2026-11-25T11:00',
                ],
                [
                    'id' => 2,
                    'empleado_id' => '',
                    'inicio' => '2026-11-25T10:00',
                    'fin' => '2026-11-25T12:00',
                ],
            ])
            ->assertSee('CUBIERTAS = 3 hr.')
            ->assertSee('SIN CUBRIR = 1 hr.');
    }

    public function test_cobertura_completa_no_deja_horas_pendientes(): void
    {
        Livewire::test(AusenciasSustituciones::class)
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('sustitutos', [
                [
                    'id' => 1,
                    'empleado_id' => '',
                    'inicio' => '2026-11-25T09:00',
                    'fin' => '2026-11-25T11:00',
                ],
                [
                    'id' => 2,
                    'empleado_id' => '',
                    'inicio' => '2026-11-25T11:00',
                    'fin' => '2026-11-25T13:00',
                ],
            ])
            ->assertSee('CUBIERTAS = 4 hr.')
            ->assertSee('SIN CUBRIR = 0 hr.');
    }

    public function test_ausencia_sin_sustitutos_queda_sin_cobertura(): void
    {
        Livewire::test(AusenciasSustituciones::class)
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('sustitutos', [])
            ->assertSee('CUBIERTAS = 0 hr.')
            ->assertSee('SIN CUBRIR = 4 hr.');
    }

    public function test_coordinador_guarda_ausencia_con_dos_sustitutos(): void
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
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $sustituto1 = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $sustituto2 = Empleado::create([
            'nombre' => 'Elena',
            'apellidos' => 'Navarro',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->set('empleadoAusenteId', $ausente->id)
            ->set('tipoAusencia', 'permiso')
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('observaciones', 'Ausencia de prueba')
            ->set('modalidadPago', 'empresa')
            ->set('sustitutos', [
                [
                    'id' => 1,
                    'empleado_id' => $sustituto1->id,
                    'inicio' => '2026-11-25T09:00',
                    'fin' => '2026-11-25T11:00',
                ],
                [
                    'id' => 2,
                    'empleado_id' => $sustituto2->id,
                    'inicio' => '2026-11-25T11:00',
                    'fin' => '2026-11-25T13:00',
                ],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ausencias', [
            'empleado_id' => $ausente->id,
            'tipo' => 'permiso',
            'estado' => Ausencia::ESTADO_PENDIENTE,
            'observaciones' => 'Ausencia de prueba',
        ]);

        $ausencia = Ausencia::where('empleado_id', $ausente->id)
            ->firstOrFail();

        $this->assertSame(
            '2026-11-25 08:00:00',
            $ausencia->fecha_hora_inicio->utc()->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-11-25 12:00:00',
            $ausencia->fecha_hora_fin->utc()->format('Y-m-d H:i:s')
        );

        $this->assertDatabaseHas('sustituciones', [
            'ausencia_id' => $ausencia->id,
            'modalidad_pago' => Sustitucion::PAGO_EMPRESA,
        ]);

        $sustitucion = Sustitucion::where('ausencia_id', $ausencia->id)
            ->firstOrFail();

        $tramos = $sustitucion->tramos()
            ->orderBy('inicio')
            ->get();

        $this->assertSame(
            '2026-11-25 08:00:00',
            $tramos[0]->inicio->utc()->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-11-25 10:00:00',
            $tramos[0]->fin->utc()->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-11-25 10:00:00',
            $tramos[1]->inicio->utc()->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-11-25 12:00:00',
            $tramos[1]->fin->utc()->format('Y-m-d H:i:s')
        );

        $this->assertDatabaseHas('sustitucion_tramos', [
            'sustitucion_id' => $sustitucion->id,
            'empleado_id' => $sustituto1->id,
        ]);

        $this->assertDatabaseHas('sustitucion_tramos', [
            'sustitucion_id' => $sustitucion->id,
            'empleado_id' => $sustituto2->id,
        ]);

        $this->assertDatabaseCount('sustitucion_tramos', 2);
        $this->assertDatabaseCount('asignaciones', 0);
        $this->assertDatabaseCount('registros_horas', 0);
    }

    public function test_si_falla_un_tramo_se_revierte_todo_el_guardado(): void
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
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $sustituto = Empleado::create([
            'nombre' => 'David',
            'apellidos' => 'Martín',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->set('empleadoAusenteId', $ausente->id)
            ->set('tipoAusencia', 'permiso')
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('modalidadPago', 'empresa')
            ->set('sustitutos', [
                [
                    'id' => 1,
                    'empleado_id' => $sustituto->id,
                    'inicio' => '2026-11-25T09:00',
                    'fin' => '2026-11-25T11:00',
                ],
                [
                    'id' => 2,
                    'empleado_id' => $sustituto->id,
                    'inicio' => '2026-11-25T12:00',
                    'fin' => '2026-11-25T14:00',
                ],
            ])
            ->call('guardar')
            ->assertHasErrors();

        $this->assertDatabaseCount('ausencias', 0);
        $this->assertDatabaseCount('sustituciones', 0);
        $this->assertDatabaseCount('sustitucion_tramos', 0);
    }

    public function test_empleado_ausente_no_puede_sustituirse_a_si_mismo(): void
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
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->set('empleadoAusenteId', $ausente->id)
            ->set('tipoAusencia', 'permiso')
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('modalidadPago', 'empresa')
            ->set('sustitutos', [
                [
                    'id' => 1,
                    'empleado_id' => $ausente->id,
                    'inicio' => '2026-11-25T09:00',
                    'fin' => '2026-11-25T11:00',
                ],
            ])
            ->call('guardar')
            ->assertHasErrors('sustitutos.0.empleado_id');

        $this->assertDatabaseCount('ausencias', 0);
        $this->assertDatabaseCount('sustituciones', 0);
        $this->assertDatabaseCount('sustitucion_tramos', 0);
    }

    public function test_doble_guardado_no_duplica_la_ausencia(): void
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
            'categoria_id' => $categoria->id,
            'activo' => true,
        ]);

        $formulario = Livewire::actingAs($usuario)
            ->test(AusenciasSustituciones::class)
            ->set('empleadoAusenteId', $ausente->id)
            ->set('tipoAusencia', 'permiso')
            ->set('fechaHoraInicio', '2026-11-25T09:00')
            ->set('fechaHoraFin', '2026-11-25T13:00')
            ->set('modalidadPago', 'empresa')
            ->set('sustitutos', []);

        $formulario->call('guardar')->assertHasNoErrors();

        // Simula un segundo clic sobre el mismo formulario.
        $formulario->call('guardar');

        $this->assertDatabaseCount('ausencias', 1);
        $this->assertDatabaseCount('sustituciones', 1);
        $this->assertDatabaseCount('sustitucion_tramos', 0);
    }
}
