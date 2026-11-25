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
}
