<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Turno extends Model
{
    use HasFactory;

    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_PUBLICADO = 'publicado';
    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'turnos';

    protected $fillable = [
        'necesidad_servicio_id',
        'proyecto_id',
        'instalacion_id',
        'actividad_id',
        'categoria_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'plazas_necesarias',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'hora_inicio' => 'datetime',
            'hora_fin' => 'datetime',
            'plazas_necesarias' => 'integer',
        ];
    }

    public function necesidadServicio(): BelongsTo
    {
        return $this->belongsTo(NecesidadServicio::class, 'necesidad_servicio_id');
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

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'turno_id');
    }

    public function asignacionesActivas(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'turno_id')->where('estado', '!=', 'cancelado');
    }

    /**
     * Duración en horas con precisión decimal considerando husos y cambios DST.
     */
    public function duracionHoras(): float
    {
        $inicio = Carbon::parse($this->hora_inicio);
        $fin = Carbon::parse($this->hora_fin);

        return round($inicio->diffInMinutes($fin) / 60, 2);
    }

    public function plazasAsignadas(): int
    {
        return $this->asignacionesActivas()->count();
    }

    public function plazasDisponibles(): int
    {
        return max(0, $this->plazas_necesarias - $this->plazasAsignadas());
    }

    /**
     * Determina el estado de cobertura del turno:
     * - cancelado
     * - con_conflicto
     * - sin_cubrir
     * - parcialmente_cubierto
     * - cubierto
     */
    public function estadoCobertura(): string
    {
        if ($this->estado === self::ESTADO_CANCELADO) {
            return 'cancelado';
        }

        // Comprobar si alguna asignación activa tiene conflicto
        $hayConflicto = $this->asignacionesActivas()->where('tiene_conflicto', true)->exists();
        if ($hayConflicto) {
            return 'con_conflicto';
        }

        $asignadas = $this->plazasAsignadas();

        if ($asignadas === 0) {
            return 'sin_cubrir';
        }

        if ($asignadas < $this->plazas_necesarias) {
            return 'parcialmente_cubierto';
        }

        return 'cubierto';
    }
}
