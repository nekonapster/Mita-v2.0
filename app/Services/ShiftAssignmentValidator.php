<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Ausencia;
use App\Models\Empleado;
use App\Models\Turno;
use Carbon\Carbon;

class ShiftAssignmentValidator
{
    /**
     * Valida si un empleado puede ser asignado a un turno determinado.
     *
     * @return array{valido: bool, errores: list<string>}
     */
    public function validarAsignacion(Turno $turno, Empleado $empleado): array
    {
        $errores = [];

        // 1. Empleado activo
        if (! $empleado->activo) {
            $errores[] = 'El empleado se encuentra inactivo en el sistema.';
        }

        // 2. Contrato vigente durante las fechas del turno
        $turnoInicio = Carbon::parse($turno->hora_inicio);
        $turnoFin = Carbon::parse($turno->hora_fin);

        if (! $empleado->tieneContratoVigenteEn($turnoInicio, $turnoFin)) {
            $errores[] = 'El empleado no dispone de un contrato laboral vigente para las fechas del turno (' . $turnoInicio->format('d/m/Y') . ').';
        }

        // 3. Categoría profesional compatible
        if ($turno->categoria_id && $empleado->categoria_id !== $turno->categoria_id) {
            $categoriaRequerida = $turno->categoria?->nombre ?? 'la requerida';
            $categoriaEmpleado = $empleado->categoria?->nombre ?? 'sin asignar';
            $errores[] = "La categoría profesional del empleado ({$categoriaEmpleado}) no coincide con la requerida por el turno ({$categoriaRequerida}).";
        }

        // 4. Asignación duplicada en el mismo turno
        $asignacionExistente = Asignacion::where('turno_id', $turno->id)
            ->where('empleado_id', $empleado->id)
            ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
            ->exists();

        if ($asignacionExistente) {
            $errores[] = 'El empleado ya está asignado a este turno.';
        }

        // 5. Plazas disponibles en el turno
        if ($turno->plazasDisponibles() <= 0) {
            $errores[] = "No quedan plazas disponibles en este turno ({$turno->plazasAsignadas()}/{$turno->plazas_necesarias} cubiertas).";
        }

        // 6. Ausencia aprobada incompatible (solapamiento temporal con una ausencia aprobada)
        $ausenciaEnConflicto = Ausencia::where('empleado_id', $empleado->id)
            ->where('estado', Ausencia::ESTADO_APROBADA)
            ->where(function ($query) use ($turnoInicio, $turnoFin) {
                // Solapamiento: inicio_ausencia < fin_turno Y fin_ausencia > inicio_turno
                $query->where('fecha_hora_inicio', '<', $turnoFin)
                    ->where('fecha_hora_fin', '>', $turnoInicio);
            })
            ->first();

        if ($ausenciaEnConflicto) {
            $tipo = ucfirst($ausenciaEnConflicto->tipo);
            $desde = Carbon::parse($ausenciaEnConflicto->fecha_hora_inicio)->format('d/m/Y H:i');
            $hasta = Carbon::parse($ausenciaEnConflicto->fecha_hora_fin)->format('d/m/Y H:i');
            $errores[] = "El empleado tiene una ausencia aprobada incompatible ({$tipo}: {$desde} a {$hasta}).";
        }

        // 7. Solapamiento con otro turno asignado (que no esté cancelado)
        $turnoSolapado = Asignacion::where('empleado_id', $empleado->id)
            ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
            ->where('turno_id', '!=', $turno->id)
            ->whereHas('turno', function ($query) use ($turnoInicio, $turnoFin) {
                $query->where('estado', '!=', Turno::ESTADO_CANCELADO)
                    ->where('hora_inicio', '<', $turnoFin)
                    ->where('hora_fin', '>', $turnoInicio);
            })
            ->with(['turno.instalacion', 'turno.actividad'])
            ->first();

        if ($turnoSolapado) {
            $inst = $turnoSolapado->turno->instalacion?->nombre ?? 'Instalación';
            $act = $turnoSolapado->turno->actividad?->nombre ?? 'Actividad';
            $hIni = Carbon::parse($turnoSolapado->turno->hora_inicio)->format('H:i');
            $hFin = Carbon::parse($turnoSolapado->turno->hora_fin)->format('H:i');
            $errores[] = "Solapamiento con otro turno asignado ({$inst} - {$act}, {$hIni} a {$hFin}).";
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
        ];
    }

    /**
     * Cuando se aprueba una ausencia, detecta turnos asignados existentes del empleado
     * que coincidan en fecha/hora y los marca con conflicto pendiente de resolver.
     */
    public function marcarConflictosPorAusencia(Ausencia $ausencia): int
    {
        if ($ausencia->estado !== Ausencia::ESTADO_APROBADA) {
            return 0;
        }

        $inicioAusencia = Carbon::parse($ausencia->fecha_hora_inicio);
        $finAusencia = Carbon::parse($ausencia->fecha_hora_fin);

        $asignacionesAfectadas = Asignacion::where('empleado_id', $ausencia->empleado_id)
            ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
            ->whereHas('turno', function ($query) use ($inicioAusencia, $finAusencia) {
                $query->where('estado', '!=', Turno::ESTADO_CANCELADO)
                    ->where('hora_inicio', '<', $finAusencia)
                    ->where('hora_fin', '>', $inicioAusencia);
            })
            ->get();

        $contador = 0;
        foreach ($asignacionesAfectadas as $asignacion) {
            $asignacion->update([
                'tiene_conflicto' => true,
                'motivo_conflicto' => "Ausencia aprobada sobrevenida ({$ausencia->tipo}) entre "
                    . $inicioAusencia->format('d/m/Y H:i') . " y " . $finAusencia->format('d/m/Y H:i'),
            ]);
            $contador++;
        }

        return $contador;
    }
}
