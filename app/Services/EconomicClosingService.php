<?php

namespace App\Services;

use App\Models\CierreEconomico;
use App\Models\Proyecto;
use App\Models\RegistroHora;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Asignacion;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class EconomicClosingService
{
    public function __construct(
        private EconomicCalculationService $economicCalculationService
    ) {}

    public function cerrarPeriodo(
        Proyecto $proyecto,
        int $anio,
        int $mes,
        User $usuario
    ): CierreEconomico {
        // Solo el usuario autenticado puede solicitar un cierre.
        // No permitimos indicar otro usuario como responsable.
        if (!Auth::check() || Auth::id() !== $usuario->id) {
            throw new AuthorizationException(
                'El usuario responsable debe ser el usuario autenticado.'
            );
        }

        // Únicamente administración y contabilidad pueden cerrar períodos.
        if (
            !$usuario->isAdmin()
            && !$usuario->roles()
                ->where('name', Role::ACCOUNTANT)
                ->exists()
        ) {
            throw new AuthorizationException(
                'No tienes permisos para realizar cierres económicos.'
            );
        }

        // Validar el período.
        if ($anio < 2000 || $anio > 9999 || $mes < 1 || $mes > 12) {
            throw ValidationException::withMessages([
                'periodo' => 'El período económico no es válido.',
            ]);
        }

        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        // No permitir cerrar meses que todavía no han terminado.
        if ($fin->isFuture()) {
            throw ValidationException::withMessages([
                'periodo' => 'No se puede cerrar un mes que todavía no ha terminado.',
            ]);
        }

        return DB::transaction(function () use (
            $proyecto,
            $anio,
            $mes,
            $usuario,
            $inicio,
            $fin
        ) {
            // Bloquear el proyecto para serializar sus cierres.
            Proyecto::query()
                ->whereKey($proyecto->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Evitar cerrar dos veces el mismo proyecto y mes.
            if (CierreEconomico::query()
                ->where('proyecto_id', $proyecto->id)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'periodo' => 'Este proyecto y mes ya están cerrados.',
                ]);
            }

            // Detectar registros pendientes de revisión.
            $horasPendientes = RegistroHora::query()
                ->where('estado', RegistroHora::ESTADO_PENDIENTE)
                ->whereHas('asignacion.turno', function ($query) use (
                    $proyecto,
                    $inicio,
                    $fin
                ) {
                    $query->where('proyecto_id', $proyecto->id)
                        ->whereBetween('fecha', [
                            $inicio->toDateString(),
                            $fin->toDateString(),
                        ]);
                })
                ->exists();

            if ($horasPendientes) {
                throw ValidationException::withMessages([
                    'horas' => 'Existen registros de horas pendientes de revisión.',
                ]);
            }

            // Detectar asignaciones activas de turnos publicados y
            // finalizados que todavía no tienen registro de horas.
            $asignacionesSinHoras = Asignacion::query()
                ->where('estado', '!=', Asignacion::ESTADO_CANCELADO)
                ->whereDoesntHave('registroHora')
                ->whereHas('turno', function ($query) use (
                    $proyecto,
                    $inicio,
                    $fin
                ) {
                    $query->where('proyecto_id', $proyecto->id)
                        ->where('estado', Turno::ESTADO_PUBLICADO)
                        ->whereBetween('fecha', [
                            $inicio->toDateString(),
                            $fin->toDateString(),
                        ])
                        ->where('hora_fin', '<=', now());
                })
                ->exists();

            if ($asignacionesSinHoras) {
                throw ValidationException::withMessages([
                    'horas' => 'Existen asignaciones de turnos finalizados '
                        . 'sin registros de horas. Deben revisarse antes '
                        . 'de cerrar el período económico.',
                ]);
            }

            // Calcular el informe económico del período.
            $informe = $this->economicCalculationService
                ->calcularInformeProyecto(
                    $proyecto,
                    $inicio,
                    $fin
                );

            // No cerrar con costes incompletos.
            if (
                $informe['coste_previsto_incompleto']
                || $informe['coste_real_incompleto']
            ) {
                throw ValidationException::withMessages([
                    'costes' => 'Existen costes de personal incompletos.',
                ]);
            }

            // Guardar el informe consolidado.
            return CierreEconomico::create([
                'proyecto_id' => $proyecto->id,
                'anio' => $anio,
                'mes' => $mes,
                'datos_consolidados' => $informe,
                'cerrado_por' => $usuario->id,
                'cerrado_en' => now(),
            ]);
        });
    }
}
