<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sustitucion extends Model
{
    protected $table = 'sustituciones';

    public const PAGO_EMPRESA = 'empresa';
    public const PAGO_EMPRESA_DESCUENTO_HORAS = 'empresa_descuento_horas';
    public const PAGO_MONITOR_SUSTITUIDO = 'monitor_sustituido';

    protected $fillable = [
        'ausencia_id',
        'turno_id',
        'modalidad_pago',
    ];

    public function ausencia(): BelongsTo
    {
        return $this->belongsTo(Ausencia::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function tramos(): HasMany
    {
        return $this->hasMany(SustitucionTramo::class);
    }


    protected static function booted(): void
    {
        static::saving(function (self $sustitucion): void {
            $modalidadesPermitidas = [
                self::PAGO_EMPRESA,
                self::PAGO_EMPRESA_DESCUENTO_HORAS,
                self::PAGO_MONITOR_SUSTITUIDO,
            ];

            if (! in_array($sustitucion->modalidad_pago, $modalidadesPermitidas, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'modalidad_pago' => 'La modalidad de pago seleccionada no es válida.',
                ]);
            }
        });
    }
}
