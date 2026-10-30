<?php

namespace App\Services;

use App\Models\TarifaCoste;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CostRatePeriodService
{
    public function existeSolapamiento(
        ?int $empleadoId,
        ?int $categoriaId,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?int $ignorarId = null
    ): bool {
        $query = TarifaCoste::query();

        // Comprobar tarifas del mismo empleado o categoría.
        if ($empleadoId !== null) {
            $query->where('empleado_id', $empleadoId);
        } else {
            $query->whereNull('empleado_id')
                ->where('categoria_id', $categoriaId);
        }

        // Al editar, excluir la propia tarifa.
        if ($ignorarId !== null) {
            $query->where('id', '!=', $ignorarId);
        }

        // Si la nueva tarifa tiene fecha de fin,
        // la existente debe comenzar antes o ese mismo día.
        if ($fechaFin !== null) {
            $query->whereDate('fecha_inicio', '<=', $fechaFin);
        }

        // La tarifa existente debe terminar después
        // del inicio de la nueva, o no tener fecha de fin.
        $query->where(function ($q) use ($fechaInicio) {
            $q->whereNull('fecha_fin')
              ->orWhereDate('fecha_fin', '>=', $fechaInicio);
        });

        return $query->exists();
    }

    public function crearExcepcionTemporalEmpleado(
    int $empleadoId,
    float $costeHora,
    string $fechaInicio,
    string $fechaFin
): void {

    // 1. Validar los datos.
    Validator::make([
        'empleado_id' => $empleadoId,
        'coste_hora' => $costeHora,
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin,
    ], [
        'empleado_id' => 'required|integer|exists:empleados,id',
        'coste_hora' => 'required|numeric|min:0',
        'fecha_inicio' => 'required|date_format:Y-m-d',
        'fecha_fin' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio',
    ])->validate();

    // 2. Ejecutar todos los cambios en una transacción.
    DB::transaction(function () use (
        $empleadoId,
        $costeHora,
        $fechaInicio,
        $fechaFin
    ) {

        // Bloquear las tarifas del empleado durante la operación.
        $tarifas = TarifaCoste::query()
            ->where('empleado_id', $empleadoId)
            ->lockForUpdate()
            ->get();

        // Buscar una tarifa personalizada indefinida
        // que comience antes de la excepción.
        $candidatas = $tarifas->filter(
            fn (TarifaCoste $tarifa) =>
                $tarifa->fecha_fin === null
                && $tarifa->fecha_inicio->toDateString() < $fechaInicio
        );

        // Por seguridad, solo admitimos una tarifa de origen.
        if ($candidatas->count() !== 1) {
            throw ValidationException::withMessages([
                'fecha_inicio' =>
                    'No existe una única tarifa personalizada indefinida '
                    . 'que pueda dividirse para este período.',
            ]);
        }

        $original = $candidatas->first();

        // Rechazar otros períodos que puedan entrar en conflicto.
        $conflicto = $tarifas->contains(
            fn (TarifaCoste $tarifa) =>
                $tarifa->id !== $original->id
                && (
                    $tarifa->fecha_fin === null
                    || $tarifa->fecha_fin->toDateString() >= $fechaInicio
                )
        );

        if ($conflicto) {
            throw ValidationException::withMessages([
                'fecha_inicio' =>
                    'Existen otras tarifas que impiden crear '
                    . 'esta excepción automáticamente.',
            ]);
        }

        // Conservar los datos originales.
        $costeOriginal = (float) $original->coste_hora;
        $observacionesOriginales = $original->observaciones;

        // 3. Finalizar la tarifa original el día anterior.
        $original->update([
            'fecha_fin' => Carbon::parse($fechaInicio)
                ->subDay()
                ->toDateString(),
        ]);

        // 4. Crear la excepción temporal.
        TarifaCoste::create([
            'empleado_id' => $empleadoId,
            'categoria_id' => null,
            'coste_hora' => $costeHora,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'observaciones' => 'Excepción temporal de tarifa',
        ]);

        // 5. Recuperar automáticamente la tarifa original.
        TarifaCoste::create([
            'empleado_id' => $empleadoId,
            'categoria_id' => null,
            'coste_hora' => $costeOriginal,
            'fecha_inicio' => Carbon::parse($fechaFin)
                ->addDay()
                ->toDateString(),
            'fecha_fin' => null,
            'observaciones' => $observacionesOriginales,
        ]);

    });
}
}