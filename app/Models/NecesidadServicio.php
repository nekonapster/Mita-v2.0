<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NecesidadServicio extends Model
{
    use HasFactory;

    protected $table = 'necesidades_servicio';

    protected $fillable = [
        'proyecto_id',
        'instalacion_id',
        'actividad_id',
        'categoria_id',
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia',
        'dias_semana',
        'hora_inicio',
        'hora_fin',
        'cruza_medianoche',
        'plazas_necesarias',
        'observaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_vigencia' => 'date',
            'fecha_fin_vigencia' => 'date',
            'dias_semana' => 'array',
            'cruza_medianoche' => 'boolean',
            'plazas_necesarias' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function instalacion(): BelongsTo
    {
        return $this->belongsTo(Instalacion::class, 'instalacion_id');
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class, 'actividad_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'necesidad_servicio_id');
    }
}
