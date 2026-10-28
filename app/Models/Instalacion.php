<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instalacion extends Model
{
    use HasFactory;

    protected $table = 'instalaciones';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'direccion',
        'persona_contacto',
        'telefono',
        'activo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'instalacion_proyecto');
    }

    public function necesidadesServicio(): HasMany
    {
        return $this->hasMany(NecesidadServicio::class, 'instalacion_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'instalacion_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'instalacion_id');
    }
}
