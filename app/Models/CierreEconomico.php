<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreEconomico extends Model
{
    protected static function booted(): void
    {
        static::updating(function (CierreEconomico $cierre) {
            throw new \LogicException(
                'No se puede modificar un cierre económico consolidado.'
            );
        });

        static::deleting(function (CierreEconomico $cierre) {
            throw new \LogicException(
                'No se puede eliminar un cierre económico consolidado.'
            );
        });
    }
    protected $table = 'cierre_economicos';

    protected $fillable = [
        'proyecto_id',
        'anio',
        'mes',
        'datos_consolidados',
        'cerrado_por',
        'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'datos_consolidados' => 'array',
            'cerrado_en' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
