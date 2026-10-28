<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'categoria_id');
    }

    public function necesidadesServicio(): HasMany
    {
        return $this->hasMany(NecesidadServicio::class, 'categoria_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class, 'categoria_id');
    }

    public function tarifasCoste(): HasMany
    {
        return $this->hasMany(TarifaCoste::class, 'categoria_id');
    }
}
