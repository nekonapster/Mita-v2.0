<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Contrato;
use App\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contrato_debe_cubrir_turno_nocturno_completo(): void
    {
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

        Contrato::create([
            'empleado_id' => $empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-15',
            'jornada_semanal_horas' => 40,
        ]);

        $this->assertFalse(
            $empleado->tieneContratoVigenteEn(
                '2026-06-15 22:00:00',
                '2026-06-16 06:00:00'
            ),
            'El contrato no debe cubrir un turno que termina al día siguiente de su vencimiento.'
        );
    }
    public function test_contrato_vigente_cubre_turno_nocturno_completo(): void
    {
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

        Contrato::create([
            'empleado_id' => $empleado->id,
            'tipo_contrato' => 'Indefinido',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-16',
            'jornada_semanal_horas' => 40,
        ]);

        $this->assertTrue(
            $empleado->tieneContratoVigenteEn(
                '2026-06-15 22:00:00',
                '2026-06-16 06:00:00'
            ),
            'El contrato debe permitir un turno completamente cubierto por su vigencia.'
        );
    }
    public function test_dos_contratos_parciales_no_cubren_un_turno_completo(): void
{
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

    Contrato::create([
        'empleado_id' => $empleado->id,
        'tipo_contrato' => 'Temporal',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-06-15',
        'jornada_semanal_horas' => 40,
    ]);

    Contrato::create([
        'empleado_id' => $empleado->id,
        'tipo_contrato' => 'Temporal',
        'fecha_inicio' => '2026-06-16',
        'fecha_fin' => '2026-12-31',
        'jornada_semanal_horas' => 40,
    ]);

    $this->assertFalse(
        $empleado->tieneContratoVigenteEn(
            '2026-06-15 22:00:00',
            '2026-06-16 06:00:00'
        ),
        'Ningún contrato individual cubre la totalidad del turno.'
    );
}
}
