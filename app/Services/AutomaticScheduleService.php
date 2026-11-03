<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Empleado;
use App\Models\Proyecto;
use App\Models\Turno;

class AutomaticScheduleService
{
    public function __construct(
        private ShiftAssignmentValidator $validator
    ) {}

    public function generar(
        Proyecto $proyecto,
        string $fechaDesde,
        string $fechaHasta,
        ?int $instalacionId = null
    ): void {

        // Trabajar únicamente sobre turnos publicados del proyecto.
        $turnos = Turno::query()
            ->where('proyecto_id', $proyecto->id)
            ->where('estado', Turno::ESTADO_PUBLICADO)
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->when(
                $instalacionId !== null,
                fn($query) => $query->where('instalacion_id', $instalacionId)
            )
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        foreach ($turnos as $turno) {

            // Nunca superar las plazas necesarias.
            $plazasLibres = $turno->plazasDisponibles();

            if ($plazasLibres === 0) {
                continue;
            }

            // Buscar trabajadores de la categoría necesaria.
            $empleados = Empleado::query()
                ->where('activo', true)
                ->where('categoria_id', $turno->categoria_id)
                ->orderBy('id')
                ->get();

            foreach ($empleados as $empleado) {

                if ($plazasLibres === 0) {
                    break;
                }

                // Evitar asignar dos veces al mismo trabajador.
                if ($turno->asignacionesActivas()
                    ->where('empleado_id', $empleado->id)
                    ->exists()
                ) {
                    continue;
                }

                // Reutilizar las validaciones laborales existentes.
                $validacion = $this->validator->validarAsignacion(
                    $turno,
                    $empleado
                );

                if (!$validacion['valido']) {
                    continue;
                }

                Asignacion::create([
                    'turno_id' => $turno->id,
                    'empleado_id' => $empleado->id,
                    'estado' => Asignacion::ESTADO_ASIGNADO,
                    'tiene_conflicto' => false,
                ]);

                $plazasLibres--;
            }
        }
    }
}
