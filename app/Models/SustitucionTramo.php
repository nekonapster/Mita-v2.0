<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SustitucionTramo extends Model
{
    protected $table = 'sustitucion_tramos';

    protected $fillable = [
        'sustitucion_id',
        'empleado_id',
        'inicio',
        'fin',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    public function sustitucion(): BelongsTo
    {
        return $this->belongsTo(Sustitucion::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }



    protected static function booted(): void
    {
        static::saving(function (self $tramo): void {
            $inicio = \Carbon\Carbon::parse($tramo->inicio);
            $fin = \Carbon\Carbon::parse($tramo->fin);

            // La finalización debe ser posterior al inicio.
            if ($fin->lessThanOrEqualTo($inicio)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fin' => 'La hora de finalización debe ser posterior a la de inicio.',
                ]);
            }

            // Comprobar que el tramo pertenece al periodo de ausencia.
            $ausencia = $tramo->sustitucion->ausencia;

            $inicioAusencia = \Carbon\Carbon::parse($ausencia->fecha_hora_inicio);
            $finAusencia = \Carbon\Carbon::parse($ausencia->fecha_hora_fin);

            if ($inicio->lessThan($inicioAusencia) || $fin->greaterThan($finAusencia)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inicio' => 'El tramo debe estar comprendido dentro del periodo de ausencia.',
                ]);
            }
        });
    }
}
