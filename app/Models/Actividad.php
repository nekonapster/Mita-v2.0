<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    use HasFactory;

    protected $table = 'actividades';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function necesidadesServicio(): HasMany
    {
        return $this->hasMany(NecesidadServicio::class, 'actividad_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'actividad_id');
    }
}
