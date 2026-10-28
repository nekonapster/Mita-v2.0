<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    use HasFactory;

    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_ACTIVO = 'activo';
    public const ESTADO_FINALIZADO = 'finalizado';
    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'proyectos';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function instalaciones(): BelongsToMany
    {
        return $this->belongsToMany(Instalacion::class, 'instalacion_proyecto');
    }

    public function necesidadesServicio(): HasMany
    {
        return $this->hasMany(NecesidadServicio::class, 'proyecto_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'proyecto_id');
    }

    public function movimientosEconomicos(): HasMany
    {
        return $this->hasMany(MovimientoEconomico::class, 'proyecto_id')->orderBy('fecha', 'desc');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'proyecto_id')->orderBy('fecha', 'desc');
    }
}
